<?php
require_once __DIR__ . '/includes/session_config.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.html");
  exit;
}
require './db_config.php';
require __DIR__ . '/helpers/report_no.php';

function getPhoneData($phone)
{
  if (empty($phone)) {
    return [
      'clean' => '',
      'display' => '-'
    ];
  }

  $clean = preg_replace('/[^0-9]/', '', $phone ?? '');
  $display = $phone;

  if (strlen($clean) == 10) {
    $display = preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1-$2-$3', $clean);
  } elseif (strlen($clean) == 9) {
    $display = preg_replace('/^(\d{2})(\d{3})(\d{4})$/', '$1-$2-$3', $clean);
  }

  return [
    'clean' => $clean,
    'display' => $display
  ];
}

// ตรวจสอบว่ามี ID ส่งมาหรือไม่
if (!isset($_GET["id"]) || empty($_GET["id"])) {
  header("Location: incident.php");
  exit;
}

$id = $_GET["id"];

$sqlData = "SELECT t1.receiveNotiReportNo,t1.id,t1.receiveNoti_No,t1.receiveNoti_No_TH,t1.location_create,DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS cvdate,t1.complaints_From,
-- case when t1.complaints_From_Device = 't' then 'ทางโทรศัพท์' ELSE 'วิทยุสื่อสาร' END AS complaintsdevice,
case 
  when t1.complaints_From_Device = 'b' then 'ทางหนังสือ'
  when t1.complaints_From_Device = 't' then 'ทางโทรศัพท์' 
  when t1.complaints_From_Device = 'r' then 'วิทยุสื่อสาร' 
  ELSE NULL 
END AS complaintsdevice,
t1.location_crime,DATE_FORMAT(t1.time_Occurrence, '%d/%m/%Y %H:%i:%s') AS cvtimeoccurrence,
case when t1.complaints_type = '01' then 'คดีเกี่ยวกับทรัพย์'
when t1.complaints_type = '02' then 'คดีเกี่ยวกับชีวิต'
when t1.complaints_type = '03' then 'คดีเกี่ยวกับระเบิด'
when t1.complaints_type = '04' then 'คดีเพลิงไหม้'
when t1.complaints_type = '05' then 'คดีจราจร'
when t1.complaints_type = '06' then 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)'
when t1.complaints_type = '07' then 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ'
when t1.complaints_type = '08' then 'ตรวจเก็บวัตถุพยานบุคคล'
ELSE 'อื่นๆ' END AS complaintstype
,CONCAT(t3.first_name,' ',t3.last_name) AS fullname
,t1.location_crime
,t1.basic_Info
,t1.inquiry_official_full_name
,t1.inquiry_official_phone
,t1.suffer_full_name
,t1.suffer_phone
,t1.up_to        
,t1.down_to      
,t1.daily_no
,t1.create_by FROM rn_ReceiveNoti t1 
LEFT JOIN users t2 ON t1.create_by = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
WHERE t1.statusDelete = 0 AND t2.is_active = 1 and t1.id = ?";
$stmt = $pdo->prepare($sqlData);
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  header("Location: incident.php");
  exit;
}

// 2. ดึงข้อมูลสำหรับ Preload ใส่ Modal (Edit Mode - Offline Support)
// Query นี้ดึงข้อมูลดิบจาก DB พร้อม JOIN เอาชื่อตำแหน่งของผู้ทบทวน/ผู้อนุมัติมาด้วย
$sqlPreload = "SELECT 
        t1.*,
        COALESCE(rank_rev.rank_name, '') as review_rank_name,
        COALESCE(rank_app.rank_name, '') as approve_rank_name,
        
        -- เช็คสถานะลายเซ็น (เพื่อ Disabled Dropdown)
        CASE WHEN sig_rev.signature IS NOT NULL THEN 1 ELSE 0 END as review_has_sig,
        CASE WHEN sig_app.signature IS NOT NULL THEN 1 ELSE 0 END as approve_has_sig

    FROM rn_ReceiveNoti t1
    
    -- Join เอาตำแหน่งผู้ทบทวน
    LEFT JOIN user_profile prof_rev ON t1.userReviewID = prof_rev.user_id
    LEFT JOIN user_rank rank_rev ON prof_rev.rank_id = rank_rev.rank_id
    
    -- Join เอาตำแหน่งผู้อนุมัติ
    LEFT JOIN user_profile prof_app ON t1.userApproveID = prof_app.user_id
    LEFT JOIN user_rank rank_app ON prof_app.rank_id = rank_app.rank_id

    -- Join เช็คสถานะการเซ็นชื่อ (ตาราง rn_ReceiveNotiApprove)
    -- *ต้องปรับ Condition ให้ตรงกับ Logic จริงของตาราง approve*
    -- สมมติว่าใน rn_ReceiveNotiApprove เก็บ ComplaintsID และ userId
    LEFT JOIN rn_ReceiveNotiApprove sig_rev ON t1.id = sig_rev.ComplaintsID AND t1.userReviewID = sig_rev.userId
    LEFT JOIN rn_ReceiveNotiApprove sig_app ON t1.id = sig_app.ComplaintsID AND t1.userApproveID = sig_app.userId

    WHERE t1.id = ?";

$stmtPreload = $pdo->prepare($sqlPreload);
$stmtPreload->execute([$id]);
$rawEditData = $stmtPreload->fetch(PDO::FETCH_ASSOC);

// เตรียม Array สำหรับส่งให้ JS (JSON)
$preloadData = [];
if ($rawEditData) {
  $preloadData = [
    'id' => $rawEditData['id'],
    'location_create' => $rawEditData['location_create'],
    // แปลงวันที่ให้เป็น Format สำหรับ <input type="datetime-local"> (YYYY-MM-DDTHH:mm)
    'create_date' => $rawEditData['create_date'] ? (new DateTime($rawEditData['create_date']))->format('Y-m-d\TH:i') : '',
    'up_to' => $rawEditData['up_to'],
    'down_to' => $rawEditData['down_to'],
    'daily_no' => $rawEditData['daily_no'] ?? '',
    'complaints_From' => $rawEditData['complaints_From'],
    'provinceID' => $rawEditData['provinceID'],
    'complaints_From_Device' => $rawEditData['complaints_From_Device'],
    'complaints_From_Device_Other' => $rawEditData['complaints_From_Device_Other'],
    'time_Occurrence' => $rawEditData['time_Occurrence'] ? (new DateTime($rawEditData['time_Occurrence']))->format('Y-m-d\TH:i') : '',
    'complaints_type' => $rawEditData['complaints_type'],
    'complaints_type_other' => $rawEditData['complaints_type_other'],
    'location_crime' => $rawEditData['location_crime'],
    'basic_Info' => $rawEditData['basic_Info'],

    // บุคคล
    'inquiry_official_first_name' => $rawEditData['inquiry_official_first_name'],
    'inquiry_official_last_name' => $rawEditData['inquiry_official_last_name'],
    'inquiry_official_phone' => $rawEditData['inquiry_official_phone'],
    'suffer_first_name' => $rawEditData['suffer_first_name'],
    'suffer_last_name' => $rawEditData['suffer_last_name'],
    'suffer_phone' => $rawEditData['suffer_phone'],

    // ผู้ทบทวน
    'user_ReviewType' => $rawEditData['userReviewType'],
    'user_ReviewTypeVal' => $rawEditData['userReviewTypeVal'], // สำหรับ Select ลำดับ/จังหวัด
    'userReviewID' => $rawEditData['userReviewID'],
    'posUserReview' => $rawEditData['review_rank_name'], // ชื่อตำแหน่งที่ Join มาได้
    'review_has_sig' => $rawEditData['review_has_sig'], // สถานะการเซ็น (1=เซ็นแล้ว)

    // ผู้อนุมัติ
    'user_ApproveType' => $rawEditData['userApproveType'],
    'user_ApproveTypeVal' => $rawEditData['userApproveTypeVal'],
    'userApproveID' => $rawEditData['userApproveID'],
    'posUserApprove' => $rawEditData['approve_rank_name'], // ชื่อตำแหน่งที่ Join มาได้
    'approve_has_sig' => $rawEditData['approve_has_sig'] // สถานะการเซ็น
  ];
}

// แปลงเป็น JSON String เตรียมไว้ Echo ใส่ Script
$incidentDataJson = json_encode([
  'status' => 'success',
  'data' => $preloadData
]);

$DocNo = !empty($user['receiveNoti_No_TH']) ? $user['receiveNoti_No_TH'] : convertDocNoToThai($user['receiveNoti_No'] ?? '');
$complaintsType = $user['complaintstype'];
$complaintsDevice = $user['complaintsdevice'];
$inquiryOfficial = $user['inquiry_official_full_name'];
// $inquiryPhone = $user['inquiry_official_phone'];
$sufferName = $user['suffer_full_name'];
// $sufferTel = $user['suffer_phone'];
$location_crime = $user['location_crime'];
$basic_Info = $user['basic_Info'];
$timeOccurrence = $user['cvtimeoccurrence'];
$complaintsFrom = $user['complaints_From'];

$up_to = $user['up_to'] ?? '';
$down_to = $user['down_to'] ?? '';
$daily_no = $user['daily_no'] ?? '';

$inquiryPhoneData = getPhoneData($user['inquiry_official_phone']);
$inquiryPhone = $inquiryPhoneData['display'];
$inquiryPhoneClean = $inquiryPhoneData['clean'];

$sufferPhoneData = getPhoneData($user['suffer_phone']);
$sufferTel = $sufferPhoneData['display'];
$sufferTelClean = $sufferPhoneData['clean'];
$receiveNotiReportNo = convertReportNoToThai($user['receiveNotiReportNo'] ?? '');
$createBy = $user['create_by'];
$createDate = $user['cvdate'];

$title = "รายละเอียดการรับแจ้งเหตุ - ระบบบริหารจัดการฐานข้อมูลวัตถุพยาน";

ob_start();
?>
<div class="d-flex align-items-center mb-4">

  <!-- <a href="javascript:history.back()" class="text-decoration-none text-secondary d-flex align-items-center fw-medium me-3 hover-text-primary"> -->
  <a href="./incident.php" class="text-decoration-none text-secondary d-flex align-items-center fw-medium me-3 hover-text-primary">
    <i class="fa-solid fa-chevron-left me-2" style="margin-top: 2px;"></i> ย้อนกลับ
  </a>

  <div class="vr opacity-25 me-3" style="height: 25px;"></div>

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="./incident.php" class="text-decoration-none text-muted hover-text-primary"><i class="fa-solid fa-folder-open me-1"></i> รายการรับแจ้งเหตุ</a></li>
      <li class="breadcrumb-item active text-primary" aria-current="page">รายละเอียดการรับแจ้งเหตุ</li>
    </ol>
  </nav>
</div>

<div class="page-header d-flex flex-wrap justify-content-between align-items-end pb-3 border-bottom">

  <div class="d-flex align-items-center">
    <div class="bg-primary rounded-pill me-3 shadow-sm align-self-stretch" style="width: 6px;"></div>
    <div class="d-flex flex-column justify-content-center">
      <h3 class="fw-bold mb-0 lh-1 d-flex align-items-baseline">
        <span class="text-secondary fs-5 me-2">เลขที่เอกสาร:</span>
        <span class="text-primary" id="docNoText"><?php echo $DocNo; ?></span>
        <button class="btn btn-link text-muted hover-tel-primary px-1 py-0 ms-2 align-self-center" onclick="copyToClipboard('<?php echo $DocNo; ?>')" title="คัดลอก DocNo">
          <i class="fa-regular fa-copy"></i>
        </button>
      </h3>

      <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center gap-1 gap-lg-2 mt-2">
        <div class="badge bg-light text-dark border d-flex align-items-center px-2 py-1">
          <span class="text-muted fw-normal me-2" style="font-size: 0.8rem;">เลขรายงาน :</span>
          <span class="fw-bold text-secondary me-2" style="font-size: 0.9rem;">
            <?= $receiveNotiReportNo ?>
          </span>
          <a href="javascript:void(0)" class="text-muted hover-tel-primary" onclick="copyToClipboard('<?= $receiveNotiReportNo ?>')" title="คัดลอก ReportNo">
            <i class="fa-regular fa-copy"></i>
          </a>
        </div>

        <div class="vr text-secondary opacity-25 mx-1 align-self-stretch d-none d-lg-block"></div>

        <div class="text-muted small d-flex align-items-center ms-1 ms-lg-0 report-date-responsive">
          <i class="fa-regular fa-clock me-1"></i>
          <span>วันที่บันทึก: <?= !empty($createDate) ? $createDate : '-' ?> น.</span>
        </div>

      </div>
    </div>
  </div>



  <div class="d-flex flex-wrap gap-2 mt-3 mt-md-0 align-items-center">

    <!-- <button type="button" class="btn btn-outline-secondary text-dark px-3 shadow-sm hover-bg-warning flex-grow-1 flex-md-grow-0" title="Export PDF">
      <i class="fa-regular fa-file-pdf text-danger"></i>
      <span class="d-none d-xl-inline ms-1">Export PDF</span>
      <span class="d-none d-xl-none d-md-inline ms-1">PDF</span>
    </button> -->

    <div class="vr opacity-25 mx-1" style="height: 40px;"></div>

    <button type="button" class="btn btn-outline-primary px-3 shadow-sm flex-grow-1 flex-md-grow-0" title="แก้ไขข้อมูล" onclick="openEditModal('<?php echo $DocNo; ?>','<?= $id ?>')" <?php echo $_SESSION['user_id'] == $createBy ? '' : 'disabled' ?>>
      <i class="fa-solid fa-pen-to-square"></i>
      <span class="d-none d-xl-inline ms-1">แก้ไขข้อมูล</span>
      <span class="d-none d-xl-none d-md-inline ms-1">แก้ไข</span>
    </button>

    <button type="button" class="btn btn-outline-danger px-3 shadow-sm flex-grow-1 flex-md-grow-0" title="ลบข้อมูล" onclick="confirmDelete('<?php echo $DocNo; ?>','<?= $id ?>')" <?php echo $_SESSION['user_id'] == $createBy ? '' : 'disabled' ?>>
      <i class="fa-regular fa-trash-can"></i>
      <span class="d-none d-xl-inline ms-1">ลบข้อมูล</span>
      <span class="d-none d-xl-none d-md-inline ms-1">ลบ</span>
    </button>

    <!-- ต้องมาดูอีกทีว่าตอน edit จะเป็นการเปิดหน้าใหม่ที่เป็นหน้า edit แยกไปเลย หรือจะใช้วิธีไหนค่อยมารปับแก้จุดนี้ -->
    <!-- <a href="./edit_incident.php?id=<?php echo $DocNo; ?>" class="btn btn-outline-primary px-3 shadow-sm flex-grow-1 flex-md-grow-0" title="แก้ไขข้อมูล">
          <i class="fa-solid fa-pen-to-square"></i>
          <span class="d-none d-xl-inline ms-1">แก้ไขข้อมูล</span>
          <span class="d-none d-xl-none d-md-inline ms-1">แก้ไข</span>
        </a> -->



  </div>
</div>

<div class="row">
  <div class="col-lg-12">
    <div class="card-incDetail">
      <div class="card-incDetail-header">
        <i class="fa-solid fa-circle-info me-2" style="margin-top: 3px;"></i> รายละเอียดการรับแจ้งเหตุ
      </div>
      <div class="card-body">
        <div class="row g-4 mb-4">
          <div class="col-md-4">
            <div class="p-3 bg-light rounded border h-100 d-flex flex-column justify-content-center">
              <div class="info-label text-secondary mb-1">เหตุที่รับแจ้ง</div>
              <div class="info-value text-danger fs-5 fw-bold text-break"><?= !empty($complaintsType) ? $complaintsType : '-' ?></div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="d-flex flex-column justify-content-center">
              <div class="mb-3">
                <div class="info-label text-center lh-lg">รับแจ้งเหตุจาก สภ./สน.</div>
                <div class="info-value text-dark fw-bold fs-5 text-center text-break lh-sm"><?= !empty($complaintsFrom) ? $complaintsFrom : '-' ?></div>
              </div>
              <div>
                <div class="info-label text-center" style="margin-bottom: 6px;">ช่องทางรับแจ้ง</div>
                <div class="info-value text-center">

                  <?php
                  $badgeClass = "bg-primary bg-opacity-10 text-primary border-primary";
                  $icon = "fa-regular fa-circle-question";
                  $textShow = $complaintsDevice;

                  if ($complaintsDevice == 't' || $complaintsDevice == 'ทางโทรศัพท์') {
                    $icon = "fa-solid fa-phone fa-flip-horizontal";
                  } elseif ($complaintsDevice == 'r' || $complaintsDevice == 'วิทยุสื่อสาร') {
                    $icon = "fa-solid fa-broadcast-tower";
                  } elseif ($complaintsDevice == 'b' || $complaintsDevice == 'ทางหนังสือ') {
                    $icon = "fa-solid fa-file-lines";
                  } elseif (empty($complaintsDevice)) {
                    $badgeClass = "bg-secondary bg-opacity-10 text-secondary border-secondary";
                    $icon = "fa-solid fa-minus";
                    $textShow = "ไม่ระบุ";
                  }
                  ?>

                  <span class="badge border rounded-pill px-3 py-2 d-inline-flex align-items-center <?= $badgeClass ?>">
                    <i class="<?= $icon ?> me-2"></i>
                    <?= $textShow ?>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="h-100 d-flex flex-column justify-content-center ps-md-2">

              <div class="info-label">
                วันเวลาที่ทราบเหตุ
              </div>

              <div class="info-value text-dark fs-5 fw-bold">
                <?= !empty($timeOccurrence) ? $timeOccurrence : '-' ?>
              </div>

              <div class="text-muted small opacity-75">
                <i class="fa-solid fa-clock me-1"></i> (ตามเวลาท้องถิ่น)
              </div>

            </div>
          </div>
        </div>

        <div class="d-flex align-items-center my-4">
          <hr class="flex-grow-1 text-muted opacity-25">
          <span class="px-3 text-primary fw-bold bg-white text-uppercase" style="letter-spacing: 0.5px;">
            <i class="fa-solid fa-users me-2"></i> บุคคลที่เกี่ยวข้อง
          </span>
          <hr class="flex-grow-1 text-muted opacity-25">
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <div class="d-flex align-items-center bg-white border rounded p-3 shadow-sm h-100 position-relative overflow-hidden card-hover-effect hover-border-primary">
              <div class="position-absolute top-0 start-0 bottom-0 bg-primary" style="width: 6px;"></div>

              <div class="icon-circle bg-primary bg-opacity-10 text-primary me-3 ms-1 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border-radius: 50%;">
                <i class="fa-solid fa-user-shield fa-lg ps-1"></i>
              </div>

              <div class="flex-grow-1" style="min-width: 0;">
                <div class="info-label text-uppercase lh-1 mb-1" style="letter-spacing: 0.5px;">พนักงานสอบสวน</div>
                <div class="info-value text-dark fw-bold mb-0 fs-6 text-break lh-sm"><?= (!empty($inquiryOfficial) && trim($inquiryOfficial) != '') ? $inquiryOfficial : '-' ?></div>
                <div class="small text-muted d-flex align-items-center">
                  <?php if (!empty($inquiryPhoneClean)): ?>
                    <a href="tel:<?= $inquiryPhoneClean ?>" class="text-decoration-none text-muted hover-tel-primary d-flex align-items-center">
                      <i class="fa-solid fa-phone fa-flip-horizontal me-2 opacity-75"></i>
                      <span style="letter-spacing: 0.25px;"><?= $inquiryPhone ?></span>
                    </a>
                  <?php else: ?>
                    <span class="text-muted"><i class="fa-solid fa-phone-slash fa-flip-horizontal me-2 opacity-50"></i> ไม่ระบุเบอร์โทรศัพท์</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="d-flex align-items-center bg-white border rounded p-3 shadow-sm h-100 position-relative overflow-hidden card-hover-effect hover-border-danger">
              <div class="position-absolute top-0 start-0 bottom-0 bg-danger" style="width: 6px;"></div>

              <div class="icon-circle bg-danger bg-opacity-10 text-danger me-3 ms-1 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border-radius: 50%;">
                <i class="fa-solid fa-user-injured fa-lg"></i>
              </div>

              <div class="flex-grow-1" style="min-width: 0;">
                <div class="info-label text-uppercase lh-1 mb-1" style="letter-spacing: 0.5px;">ผู้เสียหาย</div>
                <div class="info-value text-dark fw-bold mb-0 fs-6 text-break lh-sm"><?= (!empty($sufferName) && trim($sufferName) != '') ? $sufferName : '-' ?></div>
                <div class="small text-muted d-flex align-items-center">
                  <?php if (!empty($sufferTelClean)): ?>
                    <a href="tel:<?= $sufferTelClean ?>" class="text-decoration-none text-muted hover-tel-primary d-flex align-items-center">
                      <i class="fa-solid fa-phone fa-flip-horizontal me-2 opacity-75"></i>
                      <span style="letter-spacing: 0.25px;"><?= $sufferTel ?></span>
                    </a>
                  <?php else: ?>
                    <span class="text-muted"><i class="fa-solid fa-phone-slash fa-flip-horizontal me-2 opacity-50"></i> ไม่ระบุเบอร์โทรศัพท์</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

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
                <div class="info-label text-secondary">สถานที่เกิดเหตุ</div>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">

                  <div class="fw-bold fs-5 text-dark text-break lh-sm">
                    <?= !empty($location_crime) ? $location_crime : '-' ?>
                  </div>

                  <?php if (!empty($location_crime)): ?>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($location_crime); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 border-opacity-50 flex-shrink-0">
                      <i class="fa-solid fa-map-location-dot me-1"></i> เปิดดูใน Google Maps
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 mb-3">
            <div class="border rounded-3 overflow-hidden">
              <div class="bg-light px-3 py-2 border-bottom d-flex align-items-center justify-content-between">
                <span class="fw-bold text-secondary text-uppercase small">
                  <i class="fa-solid fa-align-left me-2"></i>รายละเอียดพฤติการณ์
                </span>
                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill fw-normal">Note</span>
              </div>

              <div class="bg-white p-3 px-md-5 px-3">
                <div class="text-dark text-break font-monospace" style="font-size: 0.9rem; line-height: 1.7; white-space: pre-wrap;"><?= !empty($basic_Info) ? trim($basic_Info) : '<span class="text-muted fst-italic opacity-75">ไม่มีข้อมูลรายละเอียดเพิ่มเติม</span>' ?></div>
              </div>
            </div>
          </div>

        </div>

      </div>
    </div>
  </div>


  <?php if ($complaintsDevice == 'b' || $complaintsDevice == 'ทางหนังสือ'): ?>
    <div class="col-md-4">
      <div class="d-flex align-items-center bg-white border rounded p-3 shadow-sm h-100 position-relative overflow-hidden hover-border-primary card-hover-effect">
        <div class="position-absolute top-0 start-0 bottom-0 bg-primary" style="width: 4px;"></div>
        <div class="icon-circle bg-primary bg-opacity-10 text-primary me-3 ms-1 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border-radius: 50%;">
          <i class="fa-solid fa-hashtag"></i>
        </div>
        <div class="flex-grow-1" style="min-width: 0;">
          <div class="info-label text-uppercase lh-1 mb-1" style="letter-spacing: 0.5px;">เลขที่หนังสือ (ที่)</div>
          <div class="info-value text-dark fw-bold mb-0 fs-6 text-break"><?= !empty($up_to) ? htmlspecialchars($up_to) : '-' ?></div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="d-flex align-items-center bg-white border rounded p-3 shadow-sm h-100 position-relative overflow-hidden hover-border-primary card-hover-effect">
        <div class="position-absolute top-0 start-0 bottom-0 bg-primary" style="width: 4px;"></div>
        <div class="icon-circle bg-primary bg-opacity-10 text-primary me-3 ms-1 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border-radius: 50%;">
          <i class="fa-regular fa-calendar-check"></i>
        </div>
        <div class="flex-grow-1" style="min-width: 0;">
          <div class="info-label text-uppercase lh-1 mb-1" style="letter-spacing: 0.5px;">ลงวันที่</div>
          <div class="info-value text-dark fw-bold mb-0 fs-6 text-break">
            <?php
            if (!empty($down_to) && $down_to !== '0000-00-00') {
              $dt = new DateTime($down_to);
              $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
              echo intval($dt->format('d')) . ' ' . $thaiMonths[intval($dt->format('m'))] . ' ' . ($dt->format('Y') + 543);
            } else {
              echo '-';
            }
            ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
    <?php else: ?>
      <div class="col-12">
      <?php endif; ?>

      <div class="d-flex align-items-center bg-white border rounded p-3 shadow-sm h-100 position-relative overflow-hidden hover-border-primary card-hover-effect">
        <div class="position-absolute top-0 start-0 bottom-0 bg-primary" style="width: 4px;"></div>
        <div class="icon-circle bg-primary bg-opacity-10 text-primary me-3 ms-1 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border-radius: 50%;">
          <i class="fa-solid fa-list-ol"></i>
        </div>
        <div class="flex-grow-1" style="min-width: 0;">
          <div class="info-label text-uppercase lh-1 mb-1" style="letter-spacing: 0.5px;">ประจำวันข้อที่</div>
          <div class="info-value text-dark fw-bold mb-0 fs-6 text-break"><?= !empty($daily_no) ? htmlspecialchars($daily_no) : '-' ?></div>
        </div>
      </div>
      </div>
    </div>

    <div class="col-lg-12 mt-4">
      <div class="card-incDetail">
        <div class="card-incDetail-header">
          <span><i class="fa-solid fa-file-signature me-2"></i> การลงนามตรวจสอบ</span>
        </div>

        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-signature table-bordered table-hover align-middle mb-0" id="signatureTable">
              <thead class="bg-light text-secondary">
                <tr>
                  <th class="text-center py-3 text-uppercase" style="width: 80px; font-weight: 600; letter-spacing: 0.5px;">ลำดับ</th>
                  <th class="text-center py-3 text-uppercase" style="min-width: 200px; font-weight: 600; letter-spacing: 0.5px;">ชื่อ - นามสกุล</th>
                  <th class="text-center py-3 text-uppercase" style="min-width: 150px; font-weight: 600; letter-spacing: 0.5px;">ตำแหน่ง</th>
                  <th class="text-center py-3 text-uppercase" style="min-width: 300px; font-weight: 600; letter-spacing: 0.5px;">ลายเซ็น</th>
                  <th class="text-center py-3 text-uppercase" style="width: 130px; font-weight: 600; letter-spacing: 0.5px;">ดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $qryTableApprove = "SELECT ApproveID,ComplaintsID,userId,positionName,fullName,seqSignature,signature,
                      DATE_FORMAT(signature_date, '%d/%m/%Y %H:%i:%s') AS signature_date FROM rn_ReceiveNotiApprove
                      WHERE ComplaintsID = ?
                      ORDER BY seqSignature";
                $stmt = $pdo->prepare($qryTableApprove);
                $stmt->execute([$_GET["id"]]);
                if ($stmt->rowCount() > 0) {
                  // วนลูปข้อมูล
                  $index = 1;
                  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                ?>
                    <tr class="table-active-row">
                      <td class="text-center">
                        <div class="avatar-circle bg-primary text-white mx-auto fw-bold shadow-sm"
                          style="width: 36px; height: 36px; line-height: 36px; border-radius: 50%; font-size: 1rem;">
                          <?= $index++ ?>
                        </div>
                      </td>
                      <td>
                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($row['fullName']) ?></div>
                      </td>
                      <td>
                        <div class="text-secondary"><?= htmlspecialchars($row['positionName']) ?></div>
                      </td>
                      <td class="text-center py-4">
                        <div class="signature-box bg-white border rounded-3 shadow-sm mx-auto position-relative overflow-hidden"
                          style="max-width: 500px; height: 160px;">
                          <?php if ($row['signature'] == "") {
                            $currentCanvasId = "sig-canvas-" . $row['ApproveID'];
                          ?>

                            <canvas id="<?= $currentCanvasId ?>" class="signature-pad"></canvas>
                            <div class="sig-placeholder-<?= $row['ApproveID'] ?> text-muted position-absolute top-50 start-50 translate-middle opacity-25" style="pointer-events: none;">
                              <i class="fa-solid fa-signature fa-2x mb-1 d-block"></i> เซ็นชื่อที่นี่
                            </div>
                          <?php } else { ?>
                            <img src="/csims/uploads/signatures/<?= $row['signature'] ?>" alt="Signature"
                              style="max-height: 140px; width: auto; object-fit: contain; background-color: white !important;">
                          <?php } ?>
                        </div>

                        <?php if ($row['signature'] == "") { ?>
                          <div class="mt-2 text-center d-flex justify-content-center gap-2">
                            <button class="btn btn-outline-warning px-3 shadow-sm btn-eraser" data-canvas="<?= $currentCanvasId ?>" onclick="toggleEraser(this, '<?= $currentCanvasId ?>')">
                              <i class="fa-solid fa-eraser me-1"></i> ยางลบ
                            </button>
                            <button class="btn btn-outline-secondary px-3 shadow-sm" onclick="clearSignature('<?= $currentCanvasId ?>')">
                              <i class="fa-solid fa-trash-can me-1"></i> ล้างลายเซ็น
                            </button>
                          </div>
                        <?php } ?>
                      </td>
                      <td class="text-center">
                        <?php if ($row["signature_date"] == "") { ?>
                          <button id="btn-save-sig-<?= $row['ApproveID'] ?>" class="btn btn-primary px-3 shadow-sm" onclick="signatureSave('<?= $row['ApproveID'] ?>','<?= $row['userId'] ?>','<?= $row['seqSignature'] ?>','<?= $row['ComplaintsID'] ?>')">
                            <i class="fa-solid fa-save me-1"></i> บันทึก
                          </button>
                        <?php } else { ?>
                          <div class="d-flex justify-content-center">
                            <label class="text-success fw-bold">
                              Complete
                              <br />
                              <?= $row["signature_date"]; ?> น.
                            </label>
                          </div>
                        <?php } ?>
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
      </div>
    </div>
</div>

<div class="modal fade" id="editIncidentModal" aria-labelledby="editIncidentModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white py-3 px-4">
        <h5 class="modal-title fw-bold" id="editIncidentModalLabel">
          <i class="fa-solid fa-pen-to-square me-1"></i> แก้ไขข้อมูล
        </h5>
        <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body bg-light p-4">
        <form method="post" id="incidentFormEdit" action="/csims/api/ReceiveNoti/update.php" novalidate>
          <input type="hidden" id="edit_doc_no" name="doc_no">
          <input type="hidden" id="id_complaintsEdit" name="id_complaintsEdit">

          <div class="d-flex justify-content-end align-items-center mb-4 pb-2 border-bottom">
            <div class="text-end">
              <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
              <span class="fs-5 fw-bold text-primary"><?php echo $DocNo; ?></span>

              <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                <span class="fw-bold text-secondary" style="font-size: 0.85rem;">
                  <?= $receiveNotiReportNo ?>
                </span>
              </div>
            </div>
          </div>

          <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
            <legend class="fieldset-header">1. ข้อมูลการรับแจ้งเหตุ</legend>

            <div class="row justify-content-start">
              <div class="col-md-2 mb-3">
                <label for="daily_no" class="form-label">ประจำวันข้อที่</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="daily_no" name="daily_no" maxlength="50" placeholder="เช่น 12">
                  <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="daily_no" title="เขียนด้วยลายมือ">
                    <i class="fas fa-pen"></i>
                  </button>
                </div>
              </div>

              <div class="col-md-7 mb-3">
                <label for="report_location" class="form-label">เขียนที่</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="report_location" name="report_location" maxlength="100">
                  <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="report_location" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                </div>
              </div>

              <div class="col-md-3 mb-3">
                <?php
                $dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d\TH:i');
                ?>
                <label for="report_datetime" class="form-label">เมื่อวันที่/เวลา</label>
                <input type="datetime-local" class="form-control" id="report_datetime" name="report_datetime" value="<?php echo $dateNow ?>">
              </div>
            </div>

            <div class="row justify-content-start">
              <div class="col-md-5 mb-3">
                <label class="form-label">รับแจ้งเหตุจาก <span class="text-danger">*</span></label>

                <div class="row g-2">
                  <div class="col-sm-12">
                    <select class="form-select" id="source_station" name="source_station" required>
                      <?php
                      $qryPoliceStation = "SELECT * FROM master_police_station ORDER BY id DESC";
                      $stmt = $pdo->query($qryPoliceStation);
                      ?>
                      <option value="" selected disabled>กรุณาเลือก</option>
                      <?php
                      while ($station = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value="' . $station['station_name'] . '">' . $station['station_name'] . '</option>';
                      }
                      ?>
                    </select>
                  </div>
                </div>
              </div>

              <!-- จังหวัด -->
              <div class="col-md-3 mb-3">
                <label class="form-label text-muted">จังหวัด <span class="text-danger">*</span></label>
                <select class="form-select" id="provineID" name="provineID" required>
                  <option value="" selected disabled>กรุณาเลือก</option>
                  <option value="90">สงขลา</option>
                  <option value="95">ยะลา</option>
                  <option value="94">ปัตตานี</option>
                  <option value="96">นราธิวาส</option>
                </select>
              </div>

              <!-- ช่องทางที่รับแจ้ง -->
              <div class="col-md-4 mb-3">
                <label class="form-label">ช่องทางที่รับแจ้ง</label>
                <select class="form-select" id="report_channel" name="report_channel">
                  <option value="" selected disabled>กรุณาเลือก</option>
                  <option value="b">ทางหนังสือ</option>
                  <option value="t">โทรศัพท์</option>
                  <option value="r">วิทยุสื่อสาร</option>
                  <option value="o">อื่น ๆ</option>
                </select>
              </div>

              <div class="col-md-12 mb-3 d-none" id="otherChannel">
                <label class="form-label">อื่นๆ โปรดระบุรายละเอียด</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="report_other_channel" name="report_other_channel" maxlength="100">
                  <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="report_other_channel" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                </div>
                <div class="invalid-feedback">กรุณาระบุรายละเอียด</div>
              </div>
            </div>

            <div class="row justify-content-start d-none" id="letterChannel">
              <div class="col-md-9 mb-3">
                <label for="up_to" class="form-label">ที่ <span class="text-danger">*</span></label>
                <div class="input-group">
                  <input type="text" class="form-control" id="up_to" name="up_to" maxlength="100">
                  <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="up_to" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                </div>
                <div class="invalid-feedback">กรุณาระบุเลขที่หนังสือ</div>
              </div>
              <div class="col-md-3 mb-3">
                <?php
                $dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
                ?>
                <label for="down_to" class="form-label">ลงวันที่ <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="down_to" name="down_to" value="<?php echo $dateNow ?>">
                <div class="invalid-feedback">กรุณาระบุวันที่ลงหนังสือ</div>
              </div>
            </div>
          </fieldset>
          <!-- เหตุที่รับแจ้ง -->
          <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
            <legend class="fieldset-header">
              2. รายละเอียดเหตุการณ์
            </legend>
            <div class="row justify-content-start">
              <div class="col-md-3 mb-3">
                <label class="form-label">
                  วันเวลาที่เกิดเหตุ
                </label>

                <input type="datetime-local" class="form-control" id="event_datetime" name="event_datetime" value="<?php echo $dateNow ?>">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">เหตุที่รับแจ้ง <span class="text-danger">*</span></label>

                <select class="form-select" id="incident_type" name="incident_type" required>
                  <option value="" selected disabled>กรุณาเลือก</option>
                  <option value="01">ทรัพย์</option>
                  <option value="02">ชีวิต</option>
                  <option value="03">ระเบิด</option>
                  <option value="04">เพลิงไหม้</option>
                  <option value="05">จราจร</option>
                  <option value="06">ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)</option>
                  <option value="07">ตรวจเก็บวัตถุพยานที่เกิดเหตุ</option>
                  <option value="08">ตรวจเก็บวัตถุพยานบุคคล</option>
                  <option value="09">อื่น ๆ</option>
                </select>
                <div class="invalid-feedback">กรุณาระบุเหตุที่รับแจ้ง</div>
              </div>
              <div class="col-md-5 mb-3 d-none" id="other_type_wrapper">
                <label class="form-label small text-muted">อื่น ๆ โปรดระบุ</label>
                <input type="text" class="form-control" id="other_type_input" name="other_type_input">
                <div class="invalid-feedback">กรุณาระบุรายละเอียด</div>
              </div>
            </div>

            <div class="row justify-content-start">
              <div class="col-md-12 mb-3">
                <label class="form-label">สถานที่เกิดเหตุ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="incident_location" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                <textarea class="form-control" id="incident_location" name="incident_location" rows="3" maxlength="500"></textarea>
              </div>
            </div>

            <div class="row justify-content-start">
              <div class="col-md-12 mb-3">
                <label class="form-label">พฤติการณ์คดี <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="initial_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                <textarea class="form-control" id="initial_detail" name="initial_detail" rows="5" maxlength="2000"></textarea>
              </div>
            </div>
          </fieldset>
          <!-- บุคคลที่เกี่ยวข้อง -->
          <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
            <legend class="fieldset-header">
              3. บุคคลที่เกี่ยวข้อง
            </legend>

            <div class="border-bottom pb-2 mb-3">
              <h6 class="fw-bold text-secondary m-0">
                พนักงานสอบสวน
              </h6>
            </div>
            <div class="row g-3 mb-5">

              <div class="col-md-4">
                <label for="inv_firstname" class="form-label small text-muted">ชื่อ</label>
                <div class="input-group">
                  <input type="text" class="form-control person-name" id="inv_firstname" name="inv_firstname">
                  <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="inv_firstname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                </div>

                <div class="invalid-feedback">กรุณาระบุชื่อ</div>
              </div>

              <div class="col-md-4">
                <label class="form-label small text-muted">นามสกุล</label>
                <div class="input-group">
                  <input type="text" class="form-control person-lastname" id="inv_lastname" name="inv_lastname">
                  <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="inv_lastname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                </div>

              </div>

              <div class="col-md-4">
                <label for="inv_phone" class="form-label small text-muted">เบอร์โทรศัพท์</label>
                <input type="tel" class="form-control person-phone" inputmode="numeric" id="inv_phone" name="inv_phone" pattern="[0-9-]*" maxlength="12">
              </div>

            </div>

            <div class="border-bottom pb-2 mb-3">
              <h6 class="fw-bold text-secondary m-0">
                ผู้เสียหาย
              </h6>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label small text-muted">ชื่อ</label>
                <div class="input-group">
                  <input type="text" class="form-control person-name" id="vic_firstname" name="vic_firstname">
                  <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="vic_firstname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                </div>

              </div>

              <div class="col-md-4">
                <label class="form-label small text-muted">นามสกุล</label>
                <div class="input-group">
                  <input type="text" class="form-control person-lastname" id="vic_lastname" name="vic_lastname">
                  <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="vic_lastname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                </div>

              </div>

              <div class="col-md-4">
                <label class="form-label small text-muted">เบอร์โทรศัพท์</label>
                <input type="tel" class="form-control person-phone" inputmode="numeric" id="vic_phone" name="vic_phone" pattern="[0-9-]*" maxlength="12">
              </div>
            </div>
          </fieldset>
          <!-- ผู้ทบทวนข้อมูล -->
          <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
            <legend class="fieldset-header">
              4. ผู้ทบทวนข้อมูล
            </legend>

            <div class="border-bottom pb-2 mb-3">
              <h6 class="fw-bold text-secondary m-0">
              </h6>
            </div>
            <div class="row g-3 mb-5">
              <div class="col-md-2 mt-4">
                <label class="form-label small text-muted">เรียน</label>
                <select class="form-select" id="user_ReviewType" name="user_ReviewType">
                  <option value="" selected disabled>กรุณาเลือก</option>
                  <option value="nvt">นวท.(สบ)</option>
                  <option value="spt">ศพฐ</option>
                  <option value="ptjv">พฐ.จว</option>
                </select>
              </div>
              <!-- นวท.สบ -->
              <div class="col-md-2 mt-4 d-none nvt">
                <label class="form-label small text-muted">ลำดับ</label>
                <select class="form-select" id="nvt" name="nvt">
                  <option value="" selected disabled>กรุณาเลือก</option>
                  <option value="1">1</option>
                  <option value="2">2</option>
                  <option value="3">3</option>
                  <option value="4">4</option>
                  <option value="5">5</option>
                </select>
              </div>

              <!-- ศพฐ -->
              <div class="col-md-2 mt-4 d-none spt">
                <label class="form-label small text-muted">ลำดับ</label>
                <select class="form-select" id="spt" name="spt">
                  <option value="" selected disabled>กรุณาเลือก</option>
                  <option value="1">1</option>
                  <option value="2">2</option>
                  <option value="3">3</option>
                  <option value="4">4</option>
                  <option value="5">5</option>
                  <option value="6">6</option>
                  <option value="7">7</option>
                  <option value="8">8</option>
                  <option value="9">9</option>
                  <option value="10">10</option>
                </select>
              </div>

              <!-- พฐ.จว -->
              <div class="col-md-2 mt-4 d-none ptjv">
                <label class="form-label small text-muted">จังหวัด</label>
                <select class="form-select" id="ptjv" name="ptjv">
                  <option value="" selected disabled>กรุณาเลือก</option>
                  <option value="90">สงขลา</option>
                  <option value="95">ยะลา</option>
                  <option value="94">ปัตตานี</option>
                  <option value="96">นราธิวาส</option>
                </select>
              </div>

              <!-- ชื่อผู้ทบทวนข้อมูล -->
              <div class="col-md-4 mt-4">
                <label class="form-label small text-muted">ผู้ทบทวนข้อมูล <span class="text-danger">*</span></label>
                <select class="form-select" id="userReviewID" name="userReviewID" required>
                  <?php
                  $qryUserReview = "SELECT t1.user_id,CONCAT(t2.short_rank,' ',t1.first_name,' ',t1.last_name) AS fullname FROM user_profile t1 
                    LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id order by t1.user_id desc";
                  $stmt = $pdo->query($qryUserReview);
                  ?>
                  <option value="" selected disabled>กรุณาเลือก</option>

                  <?php
                  while ($userReviewData = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<option value="' . $userReviewData['user_id'] . '">' . $userReviewData['fullname'] . '</option>';
                  }
                  ?>
                </select>
                <div class="invalid-feedback">กรุณาเลือกผู้ทบทวนข้อมูล</div>
              </div>

              <div class="col-md-4 col-sm-3 mt-4">
                <label class="form-label small text-muted">ตำแหน่ง</label>
                <input type="text" class="form-control person-name" id="posUserReview" name="posUserReview">
              </div>
            </div>


          </fieldset>
          <!-- ผู้อนุมัติ -->
          <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
            <legend class="fieldset-header">
              5. ผู้อนุมัติข้อมูล
            </legend>
            <div class="border-bottom pb-2 mb-3">
              <h6 class="fw-bold text-secondary m-0">
              </h6>
            </div>
            <div class="row g-3 mb-5">
              <div class="col-md-2 mt-4">
                <label class="form-label small text-muted">เรียน</label>
                <select class="form-select" id="user_ApproveType" name="user_ApproveType">
                  <option value="" selected disabled>กรุณาเลือก</option>
                  <option value="nvt">นวท.(สบ)</option>
                  <option value="spt">ศพฐ</option>
                  <option value="ptjv">พฐ.จว</option>
                </select>
              </div>
              <!-- นวท.สบ -->
              <div class="col-md-2 mt-4 d-none nvtApprove">
                <label class="form-label small text-muted">ลำดับ</label>
                <select class="form-select" id="nvtApprove" name="nvtApprove">
                  <option value="" selected disabled>กรุณาเลือก</option>
                  <option value="1">1</option>
                  <option value="2">2</option>
                  <option value="3">3</option>
                  <option value="4">4</option>
                  <option value="5">5</option>
                </select>
              </div>

              <!-- ศพฐ -->
              <div class="col-md-2 mt-4 d-none sptApprove">
                <label class="form-label small text-muted">ลำดับ</label>
                <select class="form-select" id="sptApprove" name="sptApprove">
                  <option value="" selected disabled>กรุณาเลือก</option>
                  <option value="1">1</option>
                  <option value="2">2</option>
                  <option value="3">3</option>
                  <option value="4">4</option>
                  <option value="5">5</option>
                  <option value="6">6</option>
                  <option value="7">7</option>
                  <option value="8">8</option>
                  <option value="9">9</option>
                  <option value="10">10</option>
                </select>
              </div>

              <!-- พฐ.จว -->
              <div class="col-md-2 mt-4 d-none ptjvApprove">
                <label class="form-label small text-muted">จังหวัด</label>
                <select class="form-select" id="ptjvApprove" name="ptjvApprove">
                  <option value="" selected disabled>กรุณาเลือก</option>
                  <option value="90">สงขลา</option>
                  <option value="95">ยะลา</option>
                  <option value="94">ปัตตานี</option>
                  <option value="96">นราธิวาส</option>
                </select>
              </div>

              <!-- ชื่อผู้อนุมัติข้อมูล -->
              <div class="col-md-4 mt-4">
                <label class="form-label small text-muted">ผู้อนุมัติข้อมูล <span class="text-danger">*</span></label>
                <select class="form-select" id="userApproveID" name="userApproveID" required>
                  <?php
                  $qryUserReview = "SELECT t1.user_id,CONCAT(t2.short_rank,' ',t1.first_name,' ',t1.last_name) AS fullname FROM user_profile t1 
                                        LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id order by t1.user_id desc";
                  $stmt = $pdo->query($qryUserReview);
                  ?>
                  <option value="" selected disabled>กรุณาเลือก</option>

                  <?php
                  while ($userReviewData = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<option value="' . $userReviewData['user_id'] . '">' . $userReviewData['fullname'] . '</option>';
                  }
                  ?>
                </select>
                <div class="invalid-feedback">กรุณาเลือกผู้อนุมัติข้อมูล</div>
              </div>

              <div class="col-md-4 col-sm-3 mt-4">
                <label class="form-label small text-muted">ตำแหน่ง</label>
                <input type="text" class="form-control" id="posUserApprove" name="posUserApprove">
              </div>
            </div>

          </fieldset>
          <div class="modal-footer justify-content-end">
            <button type="submit" class="btn btn-success" id="btn_save_all">
              <i class="fas fa-save me-2"></i> บันทึกข้อมูล
            </button>
            <button type="button" class="btn btn-danger js-close-modal" data-bs-dismiss="modal">
              <i class="fas fa-times me-2"></i> ยกเลิก
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();

ob_start();
?>
<script>
  window.addEventListener('online', () => {
    console.log("Network detected. Checking server...");
    let attempts = 0;
    const maxAttempts = 30;

    const checkInterval = setInterval(async () => {
      attempts++;
      const isServerReady = await checkBackendHealth();

      if (isServerReady) {
        console.log("Server Ready! Syncing...");
        clearInterval(checkInterval);

        syncEditQueue();
        syncDeleteQueue();
        syncSignatureQueue();

        updateSyncUI();
      } else {
        if (attempts >= maxAttempts) clearInterval(checkInterval);
      }
    }, 2000);
  });

  $(document).ready(function() {
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
      dropdownParent: $('#editIncidentModal'),
      dropdownAutoWidth: true
    });

    $('#userApproveID').select2({
      theme: 'bootstrap-5',
      placeholder: "กรุณาเลือก",
      allowClear: true,
      width: '100%',
      // ระบุให้ช่อง Dropdown ไปเกิดใน Modal นี้
      dropdownParent: $('#editIncidentModal'),
      dropdownAutoWidth: true
    });

    // เพิ่มตัวช่วยดึง Focus (เพื่อความชัวร์ 100%)
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

    syncEditQueue();
    syncDeleteQueue();
    syncSignatureQueue();
  });

  $('.person-phone').mask('000-000-0000', {
    onKeyPress: function(val, e, field, options) {
      let mask = val.startsWith('02') ? '00-000-0000' : '000-000-0000';
      $('.person-phone').mask(mask, options);
    }
  });

  $('#report_channel').on('change', function(e) {
    const selectedValue = $(this).val();
    const otherInput = $('#report_other_channel');
    const upToInput = $('#up_to');
    const downToInput = $('#down_to');

    // --- เคสที่ 1: ทางหนังสือ (b) ---
    if (selectedValue === 'b') {
      $('#letterChannel').removeClass('d-none');
      upToInput.prop('required', true);
      downToInput.prop('required', true);
    } else {
      $('#letterChannel').addClass('d-none');
      upToInput.prop('required', false);
      downToInput.prop('required', false);
      upToInput.val('');
      // คืนค่าลงวันที่กลับไปเป็นวันปัจจุบันเมื่อสลับไปประเภทอื่น
      downToInput.val('<?php echo (new DateTime("now", new DateTimeZone("Asia/Bangkok")))->format("Y-m-d"); ?>');
    }

    // --- เคสที่ 2: อื่นๆ (o) ---
    if (selectedValue === 'o') {
      $('#otherChannel').removeClass('d-none');
      otherInput.prop('required', true);
    } else {
      $('#otherChannel').addClass('d-none');
      otherInput.prop('required', false);
      otherInput.val('');
    }
  });

  $('#incident_type').on('change', function(e) {
    const otherInput = $('#other_type_input');
    if ($(this).val() == '09') {
      $('#other_type_wrapper').removeClass('d-none');
      otherInput.prop('required', true);
    } else {
      $('#other_type_wrapper').addClass('d-none');
      otherInput.prop('required', false);
      otherInput.val('');
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

  function findFirstInvalidInput(form) {
    if (!form) return null;
    return form.querySelector('input:invalid, select:invalid, textarea:invalid');
  }

  function handleOfflineEditSuccess(form, modalId) {
    saveEditToLocalQueue(form);

    $(modalId).modal('hide');

    Swal.fire({
      icon: 'info',
      title: 'บันทึกการแก้ไขแบบออฟไลน์',
      text: 'ระบบบันทึกการแก้ไขไว้แล้ว จะส่งให้อัตโนมัติเมื่อระบบพร้อม',
      showConfirmButton: false,
      timer: 2000
    });

    updateSyncUI();
  }

  // ฟังก์ชันเช็ค Server (เหมือนหน้า Add)
  async function checkBackendHealth() {
    try {
      const res = await fetch('/csims/api/health_check.php', {
        method: 'GET',
        cache: 'no-store'
      });
      const data = await res.json();
      return data.status === 'ok';
    } catch (err) {
      return false;
    }
  }

  function saveEditToLocalQueue(form) {
    const queue = JSON.parse(localStorage.getItem('incidentEditQueue')) || []; // แยก Queue สำหรับ Edit
    const formData = new FormData(form);
    const data = {};
    formData.forEach((value, key) => data[key] = value);

    $(form).find('input:disabled, select:disabled, textarea:disabled').each(function() {
      const name = $(this).attr('name');
      const val = $(this).val();
      if (name) {
        data[name] = val;
      }
    });

    queue.push({
      url: form.action,
      method: 'POST',
      data: data,
      saved_at: new Date().toISOString()
    });
    localStorage.setItem('incidentEditQueue', JSON.stringify(queue));
  }

  // 1. ประกาศตัวแปร Global ไว้ข้างนอกฟังก์ชัน (เพื่อให้จำสถานะได้)
  let isSyncingEdit = false;

  async function syncEditQueue() {
    // 2. เช็คว่ากำลังทำงานอยู่ไหม ถ้าใช่ให้ดีดออกทันที
    if (isSyncingEdit) {
      console.log("Sync Edit is already running...");
      return;
    }

    if (!navigator.onLine) return;

    isSyncingEdit = true;

    try {

      const backendOk = await checkBackendHealth();
      if (!backendOk) return;

      let queue = JSON.parse(localStorage.getItem('incidentEditQueue')) || [];
      if (queue.length === 0) return;

      // Toast แจ้งเตือน
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
      });
      Toast.fire({
        icon: 'info',
        title: 'กำลังส่งข้อมูลแก้ไขที่ค้างอยู่...'
      });

      let hasSynced = false;

      for (let i = 0; i < queue.length; i++) {
        const item = queue[i];
        try {
          // สร้าง FormData จาก object (เพื่อให้ส่งแบบ multipart/form-data)
          const formData = new FormData();
          for (const key in item.data) {
            if (item.data.hasOwnProperty(key)) {
              formData.append(key, item.data[key]);
            }
          }

          const res = await $.ajax({
            url: item.url,
            type: item.method,
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
          });

          if (res.status === 'success') {
            queue.splice(i, 1);
            i--;
            hasSynced = true;
          } else {
            console.error('Sync failed for item:', res);
            break;
          }
        } catch (err) {
          console.error('Sync error for item:', err);
          break;
        }
      }

      localStorage.setItem('incidentEditQueue', JSON.stringify(queue));

      if (typeof updateSyncUI === 'function') updateSyncUI();

      if (hasSynced) {
        Toast.fire({
          icon: 'success',
          title: 'ส่งข้อมูลแก้ไขครบถ้วนแล้ว'
        }).then(() => {
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        });
      }

    } catch (error) {
      console.error("Sync Error:", error);
    } finally {
      isSyncingEdit = false;
    }
  }

  // --- ส่วน Submit Form Edit ที่ปรับปรุงแล้ว ---
  $('#incidentFormEdit').on('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const modalId = '#editIncidentModal';

    // Validation (เช็คความถูกต้อง)
    if (!form.checkValidity()) {
      e.stopPropagation();
      $(form).addClass('was-validated');

      const invalidElement = findFirstInvalidInput(form);
      let $invalidElement = null;
      let isSelect2 = false;

      if (invalidElement) {
        $invalidElement = $(invalidElement);
        isSelect2 = $invalidElement.hasClass('select2-hidden-accessible');

        const elementToScroll = isSelect2 ?
          $invalidElement.next('.select2-container')[0] :
          invalidElement;

        if (invalidElement.id === 'source_station') {
          // ถ้าเป็น source_station ให้สั่ง Scroll Container ไปที่ตำแหน่ง 0 (บนสุด)
          const modalBody = invalidElement.closest('.modal-body');
          if (modalBody) {
            modalBody.scrollTo({
              top: 0,
              behavior: 'smooth'
            });
          }
        } else {
          // ถ้าเป็นตัวอื่นๆ ใช้ scrollIntoView แบบเดิม (ไว้ตรงกลาง)
          if (elementToScroll) {
            elementToScroll.scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            });
          }
        }
      }

      Swal.fire({
        icon: 'warning',
        title: 'ข้อมูลไม่ครบถ้วน',
        text: 'กรุณากรอกข้อมูลในช่องที่มีเครื่องหมาย * ให้ครบ',
        confirmButtonText: 'ตกลง',
        confirmButtonColor: '#3085d6',
        returnFocus: false
      }).then((result) => {
        if (result.isConfirmed || result.isDismissed) {
          if (invalidElement) {
            if (isSelect2) {
              $invalidElement.select2('open');
            } else {
              invalidElement.focus();
            }
          }
        }
      });
      return;
    }

    // เช็ค Offline
    if (!navigator.onLine) {
      handleOfflineEditSuccess(form, modalId);
      return;
    }

    // เช็ค Server (Backend Check)
    const backendOk = await checkBackendHealth();
    if (!backendOk) {
      handleOfflineEditSuccess(form, modalId);
      return;
    }

    // ส่ง AJAX (พร้อม Loading State)
    const $btnSave = $(this).find('button[type="submit"]'); // หาปุ่ม Submit ในฟอร์ม
    const originalBtnText = $btnSave.html();

    $.ajax({
      url: $(this).attr('action'),
      type: 'POST',
      data: new FormData(this),
      processData: false,
      contentType: false,
      dataType: 'json',
      beforeSend: function() {
        $btnSave.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
      },
      success: function(response) {
        if (response.status == "success") {
          // ลบข้อมูลที่รอส่งออกจาก queue (ถ้ามี)
          try {
            let queue = JSON.parse(localStorage.getItem('incidentEditQueue')) || [];
            const incidentId = new FormData(form).get('id');
            queue = queue.filter(item => item.data.id !== incidentId);
            localStorage.setItem('incidentEditQueue', JSON.stringify(queue));
            // อัปเดต badge
            if (typeof updateSyncUI === 'function') updateSyncUI();
          } catch(e) {
            console.error('Error clearing queue:', e);
          }

          Swal.fire({
            icon: 'success',
            title: 'บันทึกสำเร็จ',
            text: 'ข้อมูลถูกบันทึกเรียบร้อยแล้ว',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            window.location.reload();
          });
        } else {
          Swal.fire('เกิดข้อผิดพลาด', response.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
        }
      },
      error: function(xhr, status, error) {
        console.error("AJAX Error:", xhr.responseText, status, error);
        
        // เช็คว่าเป็น network error จริงๆ หรือเปล่า (ไม่ใช่ server error)
        if (status === 'timeout' || status === 'error' && xhr.status === 0) {
          // Network error / Timeout → ถือว่า offline
          handleOfflineEditSuccess(form, modalId);
        } else {
          // Server error (400, 500, etc.) → แสดง error message
          let errorMsg = 'ไม่สามารถบันทึกข้อมูลได้';
          try {
            const resp = JSON.parse(xhr.responseText);
            errorMsg = resp.message || errorMsg;
          } catch(e) {
            if (xhr.responseText) errorMsg = xhr.responseText;
          }
          Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: errorMsg,
            confirmButtonText: 'ตกลง'
          });
        }
      },
      complete: function() {
        $btnSave.prop('disabled', false).html(originalBtnText);
      }
    });
  });

  // function openEditModal(docNo, id) {
  //   $.ajax({
  //     url: "/csims/api/ReceiveNoti/getDataByID.php",
  //     type: 'GET',
  //     data: {
  //       id: id
  //     },
  //     success: function(response) {
  //       console.log("Server Response:", response);
  //       if (response.status == "success") {
  //         // ID
  //         $('#id_complaintsEdit').val(response.data.id);
  //         // เขียนที่
  //         $('#report_location').val(response.data.location_create);
  //         // เมื่อวันที่
  //         $('#report_datetime').val(response.data.create_date);
  //         // รับแจ้งเหตุจาก
  //         $('#source_station').val(response.data.complaints_From).trigger('change'); // ในกรณีที่ใช้ select 2 ให้มี trigger ด้วย
  //         // จังหวัด
  //         $('#provineID').val(response.data.provinceID).trigger('change').prop('disabled', true);
  //         // ช่องทางที่รับแจ้ง
  //         $('#report_channel').val(response.data.complaints_From_Device);
  //         // อื่นๆ
  //         $('#report_other_channel').val(response.data.complaints_From_Device_Other);
  //         // วันเวลาที่เกิดเหตุ
  //         $('#event_datetime').val(response.data.time_Occurrence);
  //         // เหตุที่รับแจ้ง
  //         $('#incident_type').val(response.data.complaints_type).trigger('change').prop('disabled', true);
  //         // อื่นๆ 
  //         $('#other_type_input').val(response.data.complaints_type_other);
  //         // สถานที่เกิดเหตุ
  //         $('#incident_location').val(response.data.location_crime);
  //         // ข้อมูลเบื้องต้น
  //         $('#initial_detail').val(response.data.basic_Info);
  //         // บุคคลที่เกี่ยวข้อง
  //         // พนักงานสอบสวน
  //         $('#inv_firstname').val(response.data.inquiry_official_first_name);
  //         $('#inv_lastname').val(response.data.inquiry_official_last_name);
  //         $('#inv_phone').val(response.data.inquiry_official_phone);
  //         // ผู้เสียหาย
  //         $('#vic_firstname').val(response.data.suffer_first_name);
  //         $('#vic_lastname').val(response.data.suffer_last_name);
  //         $('#vic_phone').val(response.data.suffer_phone);
  //         // ผู้ทบทวน ข้อมูล
  //         // เรียน
  //         $('#user_ReviewType').val(response.data.userReviewType);
  //         if ($('#user_ReviewType').val() == 'nvt') {
  //           $('.nvt').removeClass('d-none');
  //           $('#nvt').val(response.data.userReviewTypeVal);
  //         } else if ($('#user_ReviewType').val() == 'spt') {
  //           $('.spt').removeClass('d-none');
  //           $('#spt').val(response.data.userReviewTypeVal);
  //         } else if ($('#user_ReviewType').val() == 'ptjv') {
  //           $('.ptjv').removeClass('d-none');
  //           $('#ptjv').val(response.data.userReviewTypeVal);
  //         }
  //         // ชื่อผู้ทบทวนข้อมูล
  //         $('#userReviewID').val(response.data.userReviewID).trigger('change');
  //         // ผู้อนุมัติ ข้อมูล
  //         // เรียน
  //         $('#user_ApproveType').val(response.data.userApproveType);
  //         if ($('#user_ApproveType').val() == 'nvt') {
  //           $('.nvtApprove').removeClass('d-none');
  //           $('#nvtApprove').val(response.data.userApproveTypeVal);
  //         } else if ($('#user_ApproveType').val() == 'spt') {
  //           $('.sptApprove').removeClass('d-none');
  //           $('#sptApprove').val(response.data.userApproveTypeVal);
  //         } else if ($('#user_ApproveType').val() == 'ptjv') {
  //           $('.ptjvApprove').removeClass('d-none');
  //           $('#ptjvApprove').val(response.data.userApproveTypeVal);
  //         }
  //         // ชื่อผู้อนุมัติข้อมูล
  //         $('#userApproveID').val(response.data.userApproveID).trigger('change');

  //         // เช็ค ผู้ทบทวนข้อมูลว่า เซนต์ไปยัง ถ้าเซนต์แล้วไม่สามารถแก้ไขได้
  //         if (response.checkUserReview.has_signature == 1) {
  //           // ปิด dropdown
  //           $('#userReviewID').prop('disabled', true);
  //           $('#posUserReview').prop('disabled', true);
  //         }
  //         // เช็ค ผู้อนุมัติข้อมูลว่า เซนต์ไปยัง ถ้าเซนต์แล้วไม่สามารถแก้ไขได้
  //         if (response.checkUserApprove.has_signature == 1) {
  //           // ปิด dropdown
  //           $('#userApproveID').prop('disabled', true);
  //           $('#posUserApprove').prop('disabled', true);
  //         }
  //       }
  //     },
  //     error: function(xhr, status, error) {
  //       console.error("AJAX Error Status:", status);
  //       console.error("Error Detail:", error);
  //     }
  //   });

  //   let myModal = new bootstrap.Modal(document.getElementById('editIncidentModal'));
  //   myModal.show();

  //   //ดึงข้อมูลมาหยอดลงในแต่ละ field โดย identify ข้อมูลของเอกสารที่จะนำมาแสดงผ่าน docNo
  // }


  // รับข้อมูลจาก PHP (ที่เตรียมไว้ตอนโหลดหน้า)
  const preloadedIncidentData = <?php echo $incidentDataJson; ?>;

  function openEditModal(docNo, id) {
    console.log("Opening Modal for ID:", id);

    // 1. ลองใช้ข้อมูล Preload ก่อน (วิธีนี้เร็วที่สุดและรองรับ Offline)
    // เช็คว่ามีข้อมูล, สถานะ success, และ ID ตรงกันหรือไม่
    if (preloadedIncidentData && preloadedIncidentData.status === 'success' && preloadedIncidentData.data.id == id) {
      console.log("Using preloaded data for Offline Support");

      // เรียกฟังก์ชันหยอดข้อมูล
      fillModalData(preloadedIncidentData.data);

      // เปิด Modal
      let myModal = new bootstrap.Modal(document.getElementById('editIncidentModal'));
      myModal.show();
    }
    // 2. Fallback: ถ้าไม่มีข้อมูล Preload (เช่น อาจจะเป็น Logic อื่นในอนาคต) ให้ยิง AJAX
    else {
      // ถ้าไม่มีเน็ต ให้แจ้งเตือนและจบการทำงาน
      if (!navigator.onLine) {
        Swal.fire({
          icon: 'warning',
          title: 'ไม่สามารถโหลดข้อมูลได้',
          text: 'คุณกำลังออฟไลน์และไม่พบข้อมูลที่บันทึกไว้ในหน้านี้ กรุณาเชื่อมต่ออินเทอร์เน็ต',
          confirmButtonText: 'ตกลง'
        });
        return;
      }

      // ถ้ามีเน็ต ให้ยิง AJAX ไปดึงข้อมูล (Logic เดิม)
      $.ajax({
        url: "/csims/api/ReceiveNoti/getDataByID.php",
        type: 'GET',
        data: {
          id: id
        },
        beforeSend: function() {
          Swal.showLoading();
        },
        success: function(response) {
          Swal.close();
          if (response.status == "success") {
            // ใช้ฟังก์ชันเดียวกันหยอดข้อมูล
            fillModalData(response.data);

            let myModal = new bootstrap.Modal(document.getElementById('editIncidentModal'));
            myModal.show();
          } else {
            Swal.fire('Error', 'ไม่พบข้อมูล', 'error');
          }
        },
        error: function(xhr, status, error) {
          Swal.close();
          console.error("AJAX Error:", error);
          Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
        }
      });
    }
  }

  // เช็คว่ามีการเซ็นชื่อค้างอยู่ในเครื่องไหม (สำหรับ Incident นี้ และ User นี้)
  function hasLocalSignature(complaintsId, userId) {
    if (!userId) return false;

    // ดึงคิวลายเซ็นมาดู
    const queue = JSON.parse(localStorage.getItem('incidentSignatureQueue')) || [];

    // ค้นหาว่ามีรายการที่ตรงกันไหม
    // ต้องเช็คทั้ง ComplaintsID และ userId เพื่อความชัวร์
    return queue.some(item =>
      String(item.ComplaintsID) === String(complaintsId) &&
      String(item.userId) === String(userId)
    );
  }

  // ฟังก์ชันสำหรับหยอดข้อมูลลง Modal (ใช้ร่วมกันทั้ง Preload และ Ajax)
  function fillModalData(data) {
    // --- 1. ข้อมูลทั่วไป ---
    $('#edit_doc_no').val(data.receiveNoti_No); // Hidden field
    $('#id_complaintsEdit').val(data.id); // Hidden ID
    $('#report_location').val(data.location_create);
    $('#report_datetime').val(data.create_date); // Format: YYYY-MM-DDTHH:mm

    // ผูกข้อมูลฟิลด์ "ที่" และ "ลงวันที่" เข้าหน้าฟอร์มเพิ่ม/แก้ไข
    $('#up_to').val(data.up_to ?? '');
    $('#down_to').val(data.down_to ?? '');

    $('#daily_no').val(data.daily_no ?? '');

    // สถานีตำรวจ (Select2)
    $('#source_station').val(data.complaints_From).trigger('change');

    // จังหวัด
    $('#provineID').val(data.provinceID).trigger('change');
    $('#provineID').prop('disabled', true);

    // --- 2. ช่องทางรับแจ้ง (มีเงื่อนไขเปิดช่อง text) ---
    $('#report_channel').val(data.complaints_From_Device).trigger('change');
    if (data.complaints_From_Device === 'o') {
      $('#report_other_channel').val(data.complaints_From_Device_Other);
    } else {
      $('#report_other_channel').val('');
    }

    // --- 3. รายละเอียดเหตุการณ์ ---
    $('#event_datetime').val(data.time_Occurrence);

    // เหตุที่รับแจ้ง (มีเงื่อนไขเปิดช่อง text)
    $('#incident_type').val(data.complaints_type).trigger('change');
    $('#incident_type').prop('disabled', true);

    if (data.complaints_type === '09') { // 09 คือ อื่นๆ
      $('#other_type_input').val(data.complaints_type_other);
    } else {
      $('#other_type_input').val('');
    }

    $('#incident_location').val(data.location_crime);
    $('#initial_detail').val(data.basic_Info);

    // --- 4. บุคคลที่เกี่ยวข้อง ---
    // พนักงานสอบสวน
    $('#inv_firstname').val(data.inquiry_official_first_name);
    $('#inv_lastname').val(data.inquiry_official_last_name);
    $('#inv_phone').val(data.inquiry_official_phone).trigger('input'); // trigger input เพื่อให้ mask ทำงาน (ถ้ามี)

    // ผู้เสียหาย
    $('#vic_firstname').val(data.suffer_first_name);
    $('#vic_lastname').val(data.suffer_last_name);
    $('#vic_phone').val(data.suffer_phone).trigger('input');

    // --- 5. ผู้ทบทวนข้อมูล (Reviewer) ---
    // 5.1 ตั้งค่าประเภท (Rank Type)
    $('#user_ReviewType').val(data.user_ReviewType); // ใส่ค่าก่อน

    // 5.2 จัดการการแสดงผล Dropdown ย่อย (Manual Handle เพื่อความชัวร์)
    // ซ่อนทั้งหมดก่อน
    $('.nvt, .spt, .ptjv').addClass('d-none');

    // เลือกแสดงและใส่ค่าตามประเภท
    if (data.user_ReviewType == 'nvt') {
      $('.nvt').removeClass('d-none');
      $('#nvt').val(data.user_ReviewTypeVal);
    } else if (data.user_ReviewType == 'spt') {
      $('.spt').removeClass('d-none');
      $('#spt').val(data.user_ReviewTypeVal);
    } else if (data.user_ReviewType == 'ptjv') {
      $('.ptjv').removeClass('d-none');
      $('#ptjv').val(data.user_ReviewTypeVal);
    }

    // เรียก trigger เพื่อให้ Event Listener อื่นๆ (ถ้ามี) รับรู้การเปลี่ยนแปลง (แต่ UI เราจัดการไปแล้วข้างบน)
    $('#user_ReviewType').trigger('change');

    // 5.3 ใส่ชื่อผู้ทบทวน (Select2)
    $('#userReviewID').val(data.userReviewID).trigger('change');

    // 5.4 ใส่ตำแหน่ง (ใส่ทีหลัง trigger เพื่อกันไม่ให้ trigger ไปล้างค่า)
    if (data.posUserReview) {
      $('#posUserReview').val(data.posUserReview);
    }

    // 5.5 [สำคัญ] เช็คสถานะลายเซ็น (Server OR Local) เพื่อ Disabled
    const isRevServerSigned = data.review_has_sig == 1; // เซ็นแล้วใน DB
    const isRevLocalSigned = hasLocalSignature(data.id, data.userReviewID); // เซ็นแล้วในเครื่อง (รอส่ง)

    if (isRevServerSigned || isRevLocalSigned) {
      $('#userReviewID').prop('disabled', true);
      $('#posUserReview').prop('disabled', true);
    } else {
      $('#userReviewID').prop('disabled', false);
      $('#posUserReview').prop('disabled', false);
    }

    // --- 6. ผู้อนุมัติข้อมูล (Approver) ---
    // 6.1 ตั้งค่าประเภท
    $('#user_ApproveType').val(data.user_ApproveType);

    // 6.2 จัดการการแสดงผล Dropdown ย่อย
    $('.nvtApprove, .sptApprove, .ptjvApprove').addClass('d-none'); // ซ่อน class ฝั่ง Approve ทั้งหมดก่อน

    if (data.user_ApproveType == 'nvt') {
      $('.nvtApprove').removeClass('d-none');
      $('#nvtApprove').val(data.user_ApproveTypeVal);
    } else if (data.user_ApproveType == 'spt') {
      $('.sptApprove').removeClass('d-none');
      $('#sptApprove').val(data.user_ApproveTypeVal);
    } else if (data.user_ApproveType == 'ptjv') {
      $('.ptjvApprove').removeClass('d-none');
      $('#ptjvApprove').val(data.user_ApproveTypeVal);
    }

    $('#user_ApproveType').trigger('change');

    // 6.3 ใส่ชื่อผู้อนุมัติ (Select2)
    $('#userApproveID').val(data.userApproveID).trigger('change');

    // 6.4 ใส่ตำแหน่ง
    if (data.posUserApprove) {
      $('#posUserApprove').val(data.posUserApprove);
    }

    // 6.5 [สำคัญ] เช็คสถานะลายเซ็น (Server OR Local) เพื่อ Disabled
    const isAppServerSigned = data.approve_has_sig == 1;
    const isAppLocalSigned = hasLocalSignature(data.id, data.userApproveID);

    if (isAppServerSigned || isAppLocalSigned) {
      $('#userApproveID').prop('disabled', true);
      $('#posUserApprove').prop('disabled', true);
    } else {
      $('#userApproveID').prop('disabled', false);
      $('#posUserApprove').prop('disabled', false);
    }
  }

  let signaturePads = {};

  document.addEventListener('DOMContentLoaded', function() {
    // Auto-sync pending edit queue on page load (if online)
    if (navigator.onLine && typeof syncEditQueue === 'function') {
      setTimeout(syncEditQueue, 1000); // รอ 1 วิให้หน้าโหลดเสร็จก่อน
    }

    const canvases = document.querySelectorAll('.signature-pad');

    canvases.forEach((canvas) => {
      const signatureBox = canvas.parentElement;
      const canvasId = canvas.id;
      const approveId = canvasId.replace('sig-canvas-', '');

      function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = signatureBox.offsetWidth * ratio;
        canvas.height = signatureBox.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        canvas.style.width = '100%';
        canvas.style.height = '100%';
      }

      const pad = new SignaturePad(canvas, {
        backgroundColor: 'rgba(255, 255, 255, 0)',
        penColor: 'rgb(0, 0, 0)'
      });

      pad.addEventListener("beginStroke", () => {
        const placeholder = document.querySelector(`.sig-placeholder-${approveId}`);
        if (placeholder) {
          placeholder.classList.add('d-none');
        }
        // รักษาโหมดยางลบไว้ทุกครั้งที่เริ่ม stroke ใหม่
        if (eraserState[canvasId]) {
          pad.penColor = '#ffffff';
        }
      });

      signaturePads[canvasId] = pad;

      window.addEventListener("resize", resizeCanvas);
      resizeCanvas();
    });
  });

  function clearSignature(canvasId) {
    const pad = signaturePads[canvasId];
    if (pad) {
      pad.clear();
      // ปิดโหมดยางลบด้วยเมื่อล้างทั้งหมด
      setEraserMode(canvasId, false);
      const approveId = canvasId.replace('sig-canvas-', '');
      const placeholder = document.querySelector(`.sig-placeholder-${approveId}`);
      if (placeholder) {
        placeholder.classList.remove('d-none');
      }
    }
  }

  // --- ยางลบ ---
  let eraserState = {}; // เก็บสถานะยางลบแต่ละ canvas

  function setEraserMode(canvasId, on) {
    const pad = signaturePads[canvasId];
    if (!pad) return;
    eraserState[canvasId] = on;
    if (on) {
      pad.penColor = '#ffffff';
      pad.minWidth = 10;
      pad.maxWidth = 12;
    } else {
      pad.penColor = 'rgb(0, 0, 0)';
      pad.minWidth = 0.5;
      pad.maxWidth = 2.5;
    }
    // อัปเดตปุ่ม
    const btn = document.querySelector('.btn-eraser[data-canvas="' + canvasId + '"]');
    if (btn) {
      if (on) {
        btn.classList.remove('btn-outline-warning');
        btn.classList.add('btn-warning', 'active');
      } else {
        btn.classList.remove('btn-warning', 'active');
        btn.classList.add('btn-outline-warning');
      }
    }
  }

  function toggleEraser(btn, canvasId) {
    setEraserMode(canvasId, !eraserState[canvasId]);
  }

  // 1. ฟังก์ชันเก็บคิวลายเซ็น
  function saveSignatureToLocalQueue(payload) {
    const queue = JSON.parse(localStorage.getItem('incidentSignatureQueue')) || [];
    queue.push(payload);
    localStorage.setItem('incidentSignatureQueue', JSON.stringify(queue));
  }


  // 2. ปรับปรุงฟังก์ชัน signatureSave
  function signatureSave(approveId, userId, seqSignature, ComplaintsID) {
    const canvasId = 'sig-canvas-' + approveId;
    const pad = signaturePads[canvasId];

    // ... (Validation: เช็ค pad, เช็ค empty เหมือนเดิม) ...
    if (!pad || pad.isEmpty()) {
      Swal.fire({
        icon: 'warning',
        title: 'กรุณาเซ็นชื่อ',
        text: 'คุณยังไม่ได้ทำการเซ็นชื่อ กรุณาเซ็นชื่อก่อนบันทึก',
        confirmButtonText: 'ตกลง'
      });
      return;
    }

    // รวม canvas กับพื้นหลังสีขาวก่อนบันทึก (กัน eraser ทำให้โปร่งใส)
    const srcCanvas = pad.canvas;
    const tmpCanvas = document.createElement('canvas');
    tmpCanvas.width = srcCanvas.width;
    tmpCanvas.height = srcCanvas.height;
    const tmpCtx = tmpCanvas.getContext('2d');
    tmpCtx.fillStyle = '#fff';
    tmpCtx.fillRect(0, 0, tmpCanvas.width, tmpCanvas.height);
    tmpCtx.drawImage(srcCanvas, 0, 0);
    const signatureData = tmpCanvas.toDataURL('image/png');
    const payload = {
      approveID: approveId,
      signature: signatureData,
      seqSignature: seqSignature,
      ComplaintsID: ComplaintsID,
      userId: userId
    };

    // --- ส่วนที่เพิ่มใหม่: เช็ค Offline ---
    if (!navigator.onLine) {
      saveSignatureToLocalQueue(payload);
      Swal.fire({
        icon: 'info',
        title: 'บันทึกลายเซ็นออฟไลน์',
        text: 'ลายเซ็นถูกบันทึกในเครื่องแล้ว จะส่งเมื่อมีเน็ต',
        timer: 2000,
        showConfirmButton: false
      }).then(() => {
        const btnId = '#btn-save-sig-' + approveId;
        $(btnId).prop('disabled', true)
          .removeClass('btn-primary')
          .addClass('btn-warning')
          .html('<i class="fas fa-clock me-1"></i> รอส่ง...');

        // เคลียร์กระดานเซ็น
        pad.clear();
      });
      updateSyncUI();
      return;
    }
    // ------------------------------------

    $.ajax({
      url: '/csims/api/ReceiveNoti/saveSignature.php',
      type: 'POST',
      data: {
        approveID: approveId,
        signature: signatureData,
        seqSignature: seqSignature,
        ComplaintsID: ComplaintsID,
        userId: userId
      },
      dataType: 'json',
      beforeSend: function() {
        Swal.fire({
          title: 'กำลังบันทึก...',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });
      },
      success: function(response) {
        console.log(response);
        if (response.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'บันทึกสำเร็จ',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            window.location.reload();
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: response.message
          });
        }
      },
      error: function(xhr) {
        console.error(xhr);
        saveSignatureToLocalQueue(payload);
        Swal.fire({
          icon: 'info',
          title: 'บันทึกลายเซ็นออฟไลน์',
          text: 'เกิดข้อผิดพลาดในการส่ง ระบบบันทึกไว้ให้แล้ว'
        });
      }
    });
  }

  // 3. ฟังก์ชัน Sync ลายเซ็น
  async function syncSignatureQueue() {
    if (!navigator.onLine) return;
    if (!(await checkBackendHealth())) return;

    let queue = JSON.parse(localStorage.getItem('incidentSignatureQueue')) || [];
    if (queue.length === 0) return;

    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000
    });
    Toast.fire({
      icon: 'info',
      title: 'กำลังอัปโหลดลายเซ็นค้างส่ง...'
    });

    let hasSynced = false;
    for (let i = 0; i < queue.length; i++) {
      const item = queue[i];
      try {
        const res = await $.ajax({
          url: '/csims/api/ReceiveNoti/saveSignature.php',
          type: 'POST',
          data: item,
          dataType: 'json'
        });
        if (res.status === 'success') {
          queue.splice(i, 1);
          i--;
          hasSynced = true;
        } else {
          break;
        }
      } catch (e) {
        break;
      }
    }

    localStorage.setItem('incidentSignatureQueue', JSON.stringify(queue));

    if (hasSynced) {
      Toast.fire({
        icon: 'success',
        title: 'ส่งลายเซ็นครบถ้วนแล้ว'
      }).then(() => {
        setTimeout(() => window.location.reload(), 1000);
      });
    }
  }

  // function testSave(index) {
  //   const canvasId = 'sig-canvas-' + index;
  //   const pad = signaturePads[canvasId];

  //   if (!pad) {
  //     Swal.fire({
  //       icon: 'error',
  //       title: 'เกิดข้อผิดพลาด',
  //       text: 'ไม่พบกระดานเซ็นชื่อ ID: ' + canvasId,
  //       confirmButtonText: 'ตกลง',
  //       confirmButtonColor: '#dc3545'
  //     });
  //     return;
  //   }

  //   if (pad.isEmpty()) {
  //     Swal.fire({
  //       icon: 'warning',
  //       title: 'กรุณาลงนาม',
  //       text: 'คุณยังไม่ได้เซ็นชื่อในลำดับที่ ' + index + ' กรุณาเซ็นชื่อก่อนบันทึก',
  //       confirmButtonText: 'ตกลง',
  //       confirmButtonColor: '#fd7e14'
  //     });
  //     return;
  //   }

  //   const dataURL = pad.toDataURL('image/png');
  //   console.log('Saved ' + canvasId + ':', dataURL);

  //   Swal.fire({
  //     icon: 'success',
  //     title: 'บันทึกสำเร็จ',
  //     text: 'ลายเซ็นลำดับที่ ' + index + ' ถูกบันทึกเรียบร้อยแล้ว',
  //     showConfirmButton: false,
  //     timer: 1500,
  //   });

  // }

  // function testSave(val) {
  //   const pad = signaturePads[val - 1];

  //   if (!pad) {
  //     alert('ไม่พบ SignaturePad');
  //     return;
  //   }

  //   if (pad.isEmpty()) {
  //     alert('กรุณาเซ็นก่อนบันทึก');
  //     return;
  //   }

  //   const dataURL = pad.toDataURL('image/png');
  //   console.log('ลายเซ็น base64:', dataURL);
  // }


  // let signaturePads = [];

  // document.addEventListener('DOMContentLoaded', function() {

  //   const table = document.querySelector('#signatureTable');
  //   const rows = table.querySelectorAll('tbody tr');

  //   rows.forEach((row, index) => {
  //     const canvas = row.querySelector('.signature-pad');
  //     if (!canvas) return;

  //     const ratio = Math.max(window.devicePixelRatio || 1, 1);
  //     canvas.width = 200 * ratio;
  //     canvas.height = 75 * ratio;
  //     canvas.style.width = '200px';
  //     canvas.style.height = '75px';
  //     canvas.getContext('2d').scale(ratio, ratio);

  //     signaturePads[index] = new SignaturePad(canvas, {
  //       backgroundColor: 'rgba(222, 217, 217, 1)'
  //     });
  //   });

  // });

  // 1. ฟังก์ชันเก็บคิวลบ
  function saveDeleteToLocalQueue(id) {
    const queue = JSON.parse(localStorage.getItem('incidentDeleteQueue')) || [];
    // เช็คว่ามี id นี้ในคิวหรือยัง กันซ้ำ
    if (!queue.includes(id)) {
      queue.push({
        id: id,
        timestamp: new Date().toISOString()
      });
      localStorage.setItem('incidentDeleteQueue', JSON.stringify(queue));
    }
  }

  // 2. ปรับปรุงฟังก์ชัน confirmDelete
  function confirmDelete(docNo, id) {

    Swal.fire({
      icon: 'warning',
      title: 'ยืนยันการลบ ?',
      html: `คุณต้องการลบรายการ <span class="badge bg-light text-primary border align-middle fs-6 m-1">${docNo}</span> ใช่หรือไม่ ?`,
      showCancelButton: true,
      confirmButtonColor: '#089a28ff',
      cancelButtonColor: '#ea1c1cff',
      confirmButtonText: '<i class="fas fa-save me-2"></i> ยืนยัน',
      cancelButtonText: '<i class="fas fa-times me-2"></i> ยกเลิก',
      showLoaderOnConfirm: true,

      preConfirm: () => {
        // --- ส่วนที่เพิ่มใหม่: เช็ค Offline ---
        if (!navigator.onLine) {
          return Promise.resolve({
            status: 'offline'
          });
        }
        // ------------------------------------

        return new Promise((resolve, reject) => {
          $.ajax({
            url: '/csims/api/ReceiveNoti/delete.php',
            type: 'POST',
            dataType: 'json',
            data: {
              id: id
            },
            success: function(response) {
              if (response.message === 'success') resolve(response);
              else reject(response.message || 'ลบไม่สำเร็จ');
            },
            error: function() {
              reject('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
            }
          });
        }).catch(error => {
          Swal.showValidationMessage(error);
        });
      }
    }).then((result) => {
      if (result.isConfirmed) {
        // กรณี Offline
        if (result.value && result.value.status === 'offline') {
          saveDeleteToLocalQueue(id);
          Swal.fire({
            icon: 'info',
            title: 'ลบแบบออฟไลน์',
            text: 'ระบบจำคำสั่งลบไว้แล้ว จะดำเนินการเมื่อมีเน็ต',
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            if (window.history.length > 1) {
              window.history.back(); // ย้อนกลับไปหน้า List (Browser จะจำ Cache ไว้)
            } else {
              window.location.href = 'incident.php'; // กันเหนียวถ้าไม่มีประวัติ
            }
          });
        }
        // กรณี Online (สำเร็จ)
        else {
          Swal.fire({
            title: 'ลบรายการสำเร็จ!',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            window.location.href = 'incident.php';
          });
        }
      }
    })
  }

  // 3. ฟังก์ชัน Sync การลบ (เรียกใช้ใน $(document).ready และ window.online)
  async function syncDeleteQueue() {
    if (!navigator.onLine) return;
    const backendOk = await checkBackendHealth(); // ใช้ฟังก์ชันเดิมที่มี
    if (!backendOk) return;

    let queue = JSON.parse(localStorage.getItem('incidentDeleteQueue')) || [];
    if (queue.length === 0) return;

    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000
    });
    Toast.fire({
      icon: 'info',
      title: 'กำลังซิงค์รายการที่ลบ...'
    });

    for (let i = 0; i < queue.length; i++) {
      try {
        await $.ajax({
          url: '/csims/api/ReceiveNoti/delete.php',
          type: 'POST',
          data: {
            id: queue[i].id
          }
        });
        // ลบออกจากคิวไม่ว่าจะสำเร็จหรือไม่ (เพื่อไม่ให้ค้าง)
        queue.splice(i, 1);
        i--;
      } catch (e) {
        break;
      }
    }

    localStorage.setItem('incidentDeleteQueue', JSON.stringify(queue));
    Toast.fire({
      icon: 'success',
      title: 'ซิงค์รายการลบเรียบร้อย'
    });
  }

  function copyToClipboard(text) {

    if (!navigator.clipboard) {
      fallbackCopyTextToClipboard(text);
      return;
    }

    navigator.clipboard.writeText(text).then(() => {
      showCopySuccess();
    }, function(err) {
      console.error('Async: Could not copy text: ', err);
      fallbackCopyTextToClipboard(text);
    });
  }

  function fallbackCopyTextToClipboard(text) {
    let textArea = document.createElement("textarea");
    textArea.value = text;

    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";

    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
      let successful = document.execCommand('copy');
      let msg = successful ? 'successful' : 'unsuccessful';
      if (successful) {
        showCopySuccess();
      }
    } catch (err) {
      console.error('Fallback: Oops, unable to copy', err);
    }

    document.body.removeChild(textArea);
  }

  function showCopySuccess() {
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 1500,
      timerProgressBar: true,
      didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
      }
    });

    Toast.fire({
      icon: 'success',
      title: 'คัดลอกเรียบร้อย'
    });
  }
</script>

<!-- ========================================================================
     Handwriting Recognition Modal + Script
     ======================================================================== -->
<style>
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
    var hwAccumulatedText = '';
    var hwHasDrawn = false;
    var hwStrokeCount = 0;
    var hwModalInitialized = false;

    $(document).off('click.hwOpen').on('click.hwOpen', '.btn-hw-open', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var targets = ($(this).data('hw-targets') || '').split(',').map(function(s) {
        return s.trim();
      }).filter(Boolean);
      hwTargetIds = targets;
      hwAccumulatedText = '';
      hwHasDrawn = false;
      hwStrokeCount = 0;
      $('#hwAccumulated').val('');
      $('#hwResults').html('<p class="text-muted small mb-0"><i class="fas fa-arrow-down me-1"></i>ผลลัพธ์จะแสดงที่นี่หลังกด "แปลงเป็นข้อความ"</p>');
      $('#hwStatus').text('');
      $('#hwPlaceholder').removeClass('hidden');

      var modalEl = document.getElementById('hwModal');
      var modal = bootstrap.Modal.getInstance(modalEl);
      if (!modal) modal = new bootstrap.Modal(modalEl);
      modal.show();
    });

    if (!hwModalInitialized) {
      hwModalInitialized = true;
      var $modal = $('#hwModal');

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

      $modal.off('shown.bs.modal.hw').on('shown.bs.modal.hw', function() {
        if (hwCanvasInstance) {
          var oldCvs = hwCanvasInstance.canvas;
          var newCvs = oldCvs.cloneNode(true);
          oldCvs.parentNode.replaceChild(newCvs, oldCvs);
        }
        var canvas = document.getElementById('hwCanvas');
        var area = document.getElementById('hwCanvasArea');
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
      });

      $modal.off('mousedown.hw touchstart.hw').on('mousedown.hw touchstart.hw', '#hwCanvas', function() {
        $('#hwPlaceholder').addClass('hidden');
      });
      $modal.off('mousemove.hw touchmove.hw').on('mousemove.hw touchmove.hw', '#hwCanvas', function() {
        hwStrokeCount++;
        if (hwStrokeCount > 2) hwHasDrawn = true;
      });

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

      $(document).off('click.hwPenSize').on('click.hwPenSize', '.hw-pen-size-group .btn', function() {
        $('.hw-pen-size-group .btn').removeClass('active');
        $(this).addClass('active');
        if (hwCanvasInstance) hwCanvasInstance.setLineWidth(parseInt($(this).data('size')));
      });

      $('#btnHwConfirm').off('click.hw').on('click.hw', function() {
        if (hwAccumulatedText.trim() && hwTargetIds.length > 0) {
          hwTargetIds.forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
              var existing = (el.value || '').trim();
              el.value = existing ? existing + hwAccumulatedText : hwAccumulatedText;
              $(el).trigger('change').trigger('input');
            }
          });
        }
        var modalEl = document.getElementById('hwModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      });
    }

    function hwAppendText(text) {
      hwAccumulatedText += text;
      $('#hwAccumulated').val(hwAccumulatedText);
    }
  })();
</script>

<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>