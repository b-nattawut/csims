
<?php
// เริ่ม Session ที่นี่ทีเดียว (ถ้าหน้าลูกเรียก layout ไฟล์นี้จะรันก่อน)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// เช็ค Login (Data Global)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

// Log User Activity
require_once 'db_config.php';
require_once 'includes/activity_logger.php';

$sessionUserStmt = $pdo->prepare("SELECT email, role, is_active FROM users WHERE user_id = ? LIMIT 1");
$sessionUserStmt->execute([$_SESSION['user_id']]);
$sessionUser = $sessionUserStmt->fetch(PDO::FETCH_ASSOC);

if (!$sessionUser || (int)($sessionUser['is_active'] ?? 0) !== 1) {
    session_unset();
    session_destroy();
    header("Location: login.html");
    exit;
}

$_SESSION['email'] = $sessionUser['email'];
$_SESSION['role'] = $sessionUser['role'];

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = isset($title) ? str_replace(' - ', ' | ', $title) : $current_page;
logUserActivity($pdo, $_SESSION['user_id'], $page_title, $_SERVER['REQUEST_URI']);

// เตรียมข้อมูล User (ใช้ได้ทุกหน้า)
$logged_in_user = [
    'first_name' => $_SESSION['first_name'] ?? '',
    'last_name' => $_SESSION['last_name'] ?? '',
    'rank_name' => $_SESSION['rank_name'] ?? '',
    'position_name' => $_SESSION['position_name'] ?? '',
    'email' => $_SESSION['email'] ?? ''
];

$display_name = (isset($logged_in_user['rank_name']) ? $logged_in_user['rank_name'] . ' ' : '') . $logged_in_user['first_name'] . ' ' . $logged_in_user['last_name'];
$display_position = $logged_in_user['position_name'];

// ดึงรายชื่อเจ้าหน้าที่ตำรวจ
$qryOfficers = "SELECT t1.user_id, 
    CONCAT(IFNULL(t2.rank_name,''),' ',t1.first_name,' ',t1.last_name) AS fullname,
    IFNULL(t3.position_name,'') AS position_name
    FROM user_profile t1
    LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
    LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
    INNER JOIN users t4 ON t1.user_id = t4.user_id AND t4.is_active = 1
    ORDER BY t2.rank_name, t1.first_name ASC";
$stmtOfficers = $pdo->query($qryOfficers);
$officers = $stmtOfficers->fetchAll(PDO::FETCH_ASSOC);

?>

<!-- Modal: ขอขยายเวลาการออกรายงาน (บันทึกข้อความ) -->
<div class="modal fade" id="modalReportExtension" tabindex="-1" aria-labelledby="modalReportExtensionLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <!-- Modal Header -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h6 class="modal-title mb-0" id="modalReportExtensionLabel">
                    <i class="fas fa-plus me-2"></i>เพิ่มรายการ
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-0">
                <form id="formReportExtension" autocomplete="off">
                    <input type="hidden" id="rex_incident_id" name="rex_incident_id" value="">

                    <!-- Form เสมือนเอกสารราชการ -->
                    <div class="report-extension-paper mx-auto my-3" style="max-width: 900px; background: #fff; border: 1px solid #ccc; padding: 50px 60px; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px; line-height: 1.8; position: relative;">

                        <!-- ครุฑ + หัวเรื่อง -->
                        <div class="d-flex align-items-center" style="margin-bottom: 20px; min-height: 60px;">
                            <img src="images/bird.png" alt="ครุฑ" style="height: 60px;" onerror="this.style.display='none'">
                            <h4 class="flex-grow-1 text-center mb-0" style="font-family: 'TH Sarabun New', 'Sarabun', serif; font-weight: bold; font-size: 24px;">บันทึกข้อความ</h4>
                        </div>

                        <!-- ส่วนราชการ / โทร / เลขรายงาน -->
                        <div class="row mb-1" style="font-size: 16px; margin-top: 15px;">
                            <div class="col-4">
                                <span class="fw-bold">ส่วนราชการ</span>
                                <span class="ms-2" style="font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;">กสก.ศพฐ.๑</span>
                            </div>
                            <div class="col-4">
                                <span class="fw-bold">โทร.</span>
                                <span style="font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;">๐-๒๕๒๙-๒๒๓๓</span>
                            </div>
                            <div class="col-4">
                                <span class="fw-bold">เลขรายงาน</span>
                                <span id="rex_report_no_display" class="ms-1" style="font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;"></span>
                            </div>
                        </div>

                        <!-- ที่ / วันที่ -->
                        <div class="row mb-1" style="font-size: 16px;">
                            <div class="col-7">
                                <span class="fw-bold">ที่ ๐๐๓๒.๔๒/-</span>
                            </div>
                            <div class="col-5">
                                <span class="fw-bold">วันที่</span>
                                <input type="date" class="form-control form-control-sm d-inline-block border-0 rounded-0" style="width: 73%; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;" id="rex_date" name="rex_date" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>

                        <!-- เรื่อง -->
                        <div class="mb-1" style="font-size: 16px;">
                            <span class="fw-bold">เรื่อง</span>
                            <span class="ms-2">ขอขยายเวลาการออกรายงานตรวจสถานที่เกิดเหตุ</span>
                        </div>

                        <!-- เรียน -->
                        <div class="mb-3" style="font-size: 16px;">
                            <span class="fw-bold">เรียน</span>
                            <span class="ms-2">นวท.(สบ ๔)กสก.ศพฐ.๑</span>
                        </div>

                        <!-- เนื้อหาย่อหน้าแรก -->
                        <div class="mb-3" style="font-size: 16px; text-indent: 60px;">
                            <span>ด้วยข้าฯ <?=  $display_name ?> นวท.(สบ ๒) กสก.ศพฐ.๑</span>
                            <span>ผู้ตรวจสถานที่เกิดเหตุ คดีเกี่ยวกับ</span>
                            <span id="rex_complaints_type_display" style="text-decoration: underline;"></span>
                            <span>เหตุเกิดที่</span>
                            <input type="text" class="border-0 border-bottom rounded-0" style="width: 72%; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px; outline: none; padding: 0 2px;" id="rex_case_about" name="rex_case_about">
                            <span>รับแจ้งเหตุและเดินทางไปตรวจสถานที่เกิดเหตุเมื่อวันที่</span>
                            <input type="date" class="form-control form-control-sm d-inline-block border-0 border-bottom rounded-0" style="width: 20%; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;" id="rex_order_date" name="rex_order_date">
                            <span>และจะครบกำหนดออกรายงานวันที่</span>
                            <input type="date" class="form-control form-control-sm d-inline-block border-0 border-bottom rounded-0" style="width: 20%; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;" id="rex_due_date" name="rex_due_date">
                            <span>แต่เนื่องจากข้าฯไม่สามารถจัดทำรายงานเสร็จภายในกำหนด ตามคำสั่ง ศพฐ. ที่ ๔๘๐/๒๕๖๓</span>
                            <span>ลงวันที่</span>
                            <input type="date" class="form-control form-control-sm d-inline-block border-0 border-bottom rounded-0" style="width: 20%; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;" id="rex_command_date" name="rex_command_date">
                            <span>โดยรายงานดังกล่าว</span>
                        </div>

                        <!-- Checkboxes เหตุผล -->
                        <div class="mb-2 ms-4" style="font-size: 16px;">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="rex_reason_1" name="rex_reasons[]" value="อยู่ระหว่างรอผลการตรวจพิสูจน์วัตถุพยานของกลุ่มงานตรวจทางเคมี ฟิสิกส์ ศูนย์พิสูจน์หลักฐาน">
                                <label class="form-check-label" for="rex_reason_1">
                                    อยู่ระหว่างรอผลการตรวจพิสูจน์วัตถุพยานของกลุ่มงานตรวจทางเคมี ฟิสิกส์ ศูนย์พิสูจน์หลักฐาน
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="rex_reason_2" name="rex_reasons[]" value="ของกลางที่ทำการตรวจพิสูจน์มีจำนวนมาก และ/หรือ ต้องใช้เทคนิคและเวลาในการตรวจพิสูจน์">
                                <label class="form-check-label" for="rex_reason_2">
                                    ของกลางที่ทำการตรวจพิสูจน์มีจำนวนมาก และ/หรือ ต้องใช้เทคนิคและเวลาในการตรวจพิสูจน์
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="rex_reason_3" name="rex_reasons[]" value="มีเรื่อง(คดี) ที่ถูกอกรรรจ์ สะเทือนขวัญ ที่ผู้บังคับบัญชาสนใจ ซึ่งต้องทำการจัดทำรายงานผลการตรวจก่อน">
                                <label class="form-check-label" for="rex_reason_3">
                                    มีเรื่อง(คดี) ที่ถูกอกรรรจ์ สะเทือนขวัญ ที่ผู้บังคับบัญชาสนใจ ซึ่งต้องทำการจัดทำรายงานผลการตรวจก่อน
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="rex_reason_4" name="rex_reasons[]" value="อื่นๆ">
                                <label class="form-check-label" for="rex_reason_4">อื่นๆ</label>
                                <input type="text" class="form-control form-control-sm d-inline-block border-0 border-bottom rounded-0 ms-2" style="width: 60%; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;" id="rex_reason_other_text" name="rex_reason_other_text">
                            </div>
                        </div>

                        <!-- จึงขอขยายเวลา -->
                        <div class="mb-3" style="font-size: 16px; text-indent: 60px;">
                            <span>จึงขอขยายเวลาจัดทำรายงานการตรวจสถานที่เกิดเหตุ ในคดีดังกล่าวเป็นครั้งที่</span>
                            <input type="text" class="form-control form-control-sm d-inline-block border-0 border-bottom rounded-0 text-center" style="width: 50px; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;" id="rex_extension_count" name="rex_extension_count">
                        </div>

                        <div class="mb-4" style="font-size: 16px; text-indent: 60px;">
                            <span>จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติขยายเวลาการออกรายงานตรวจสถานที่เกิดเหตุ</span>
                        </div>

                        <!-- ลงชื่อผู้ขอ -->
                        <div class="mt-5 mb-4" style="text-align: center; margin-left: auto; margin-right: 60px; width: 300px; float: right;">
                            <div class="mb-1" style="font-size: 16px;">
                                <select class="form-select form-select-sm border-0 border-bottom rounded-0 text-center" style="width: 100%; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px; background-image: none; padding-right: 8px;" id="rex_requester_name" name="rex_requester_name">
                                    <option value="">กรุณาเลือก</option>
                                    <?php foreach ($officers as $officer): ?>
                                        <option value="<?= htmlspecialchars($officer['fullname'], ENT_QUOTES, 'UTF-8') ?>" data-position="<?= htmlspecialchars($officer['position_name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($officer['fullname'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-1" style="font-size: 16px;">
                                <span>(</span>
                                <span style="display: inline-block; width: 200px; text-align: center;">&nbsp;</span>
                                <span>)</span>
                            </div>
                            <div style="font-size: 16px;">
                                <span>นวท.(สบ ๒)กสก.ศพฐ.๑</span>
                            </div>
                        </div>
                        <div style="clear: both;"></div>

                        <!-- ส่วนอนุมัติ -->
                        <div class="mb-3" style="font-size: 16px;">
                            <span>-</span>
                            <span class="ms-2">อนุมัติขยายเวลาครั้งที่ ๑</span>
                            <span>จำนวน</span>
                            <input type="text" class="form-control form-control-sm d-inline-block border-0 border-bottom rounded-0 text-center" style="width: 50px; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;" id="rex_approve_days" name="rex_approve_days">
                            <span>วัน</span>
                        </div>

                        <div class="mb-4" style="font-size: 16px;">
                            <span>(</span>
                            <span>ตั้งแต่วันที่</span>
                            <input type="date" class="form-control form-control-sm d-inline-block border-0 border-bottom rounded-0" style="width: 22%; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;" id="rex_approve_start_date" name="rex_approve_start_date">
                            <span>–</span>
                            <input type="date" class="form-control form-control-sm d-inline-block border-0 border-bottom rounded-0" style="width: 22%; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;" id="rex_approve_end_date" name="rex_approve_end_date">
                            <span>)</span>
                        </div>

                        <!-- ลงชื่อผู้อนุมัติ -->
                        <div class="mt-4 mb-3" style="text-align: center; width: 300px; float: left;">
                            <div class="mb-1" style="font-size: 16px;">
                                <select class="form-select form-select-sm border-0 border-bottom rounded-0 text-center" style="width: 100%; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px; background-image: none; padding-right: 8px;" id="rex_approver_name" name="rex_approver_name">
                                    <option value="">กรุณาเลือก</option>
                                    <?php foreach ($officers as $officer): ?>
                                        <option value="<?= htmlspecialchars($officer['fullname'], ENT_QUOTES, 'UTF-8') ?>" data-position="<?= htmlspecialchars($officer['position_name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($officer['fullname'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-1" style="font-size: 16px;">
                                <span>(</span>
                                <span style="display: inline-block; width: 200px; text-align: center;">&nbsp;</span>
                                <span>)</span>
                            </div>
                            <div style="font-size: 16px;">
                                <span>นวท.(สบ ๔)กสก.ศพฐ.๑</span>
                            </div>
                            <div class="mt-2" style="font-size: 16px;">
                                <input type="date" class="form-control form-control-sm d-inline-block border-0 border-bottom rounded-0 text-center" style="width: 200px; font-family: 'TH Sarabun New', 'Sarabun', serif; font-size: 16px;" id="rex_approve_date" name="rex_approve_date">
                            </div>
                        </div>
                        <div style="clear: both;"></div>

                    </div><!-- end paper -->
                </form>
            </div><!-- end modal-body -->

            <!-- Modal Footer -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_report_extension">
                    <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
            </div>

        </div>
    </div>
</div>
