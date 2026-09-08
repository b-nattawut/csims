<?php
require_once __DIR__ . '/includes/session_config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require __DIR__ . '/helpers/report_no.php';
require_once __DIR__ . '/api/reportReview/_helpers.php';

$incidentId = (int)($_GET['id'] ?? 0);
if ($incidentId <= 0) {
    header('Location: incidentReportReview.php');
    exit;
}

try {
    $incident = rrGetIncident($pdo, $incidentId);
} catch (Throwable $e) {
    $incident = null;
}
if (!$incident) {
    header('Location: incidentReportReview.php');
    exit;
}

if (empty($incident['receiveNotiReportNo_TH'])) {
    $incident['receiveNotiReportNo_TH'] = convertReportNoToThai($incident['receiveNotiReportNo'] ?? '');
}
if (empty($incident['receiveNoti_No_TH'])) {
    $incident['receiveNoti_No_TH'] = convertDocNoToThai($incident['receiveNoti_No'] ?? '');
}

$complaintMap = [
    '01' => 'ทรัพย์', '02' => 'ชีวิต', '03' => 'ระเบิด', '04' => 'เพลิงไหม้', '05' => 'จราจร',
    '06' => 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)', '07' => 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ', '08' => 'ตรวจเก็บวัตถุพยานบุคคล',
];
$complaintName = $complaintMap[$incident['complaints_type'] ?? ''] ?? ($incident['complaints_type'] ?? '-');

$qryUsers = "SELECT t1.user_id, TRIM(CONCAT(COALESCE(t2.short_rank, t2.rank_name, ''), ' ', COALESCE(t1.first_name, ''), ' ', COALESCE(t1.last_name, ''))) AS fullname
             FROM user_profile t1
             LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
             LEFT JOIN users t3 ON t1.user_id = t3.user_id
             WHERE t3.is_active = 1
             ORDER BY t1.user_id DESC";
$userList = $pdo->query($qryUsers)->fetchAll(PDO::FETCH_ASSOC);

$docNo = $incident['receiveNoti_No_TH'] ?: ($incident['receiveNoti_No'] ?? '-');
$reportNo = $incident['receiveNotiReportNo_TH'] ?: ($incident['receiveNotiReportNo'] ?? '-');

$title = "รายละเอียดการตรวจร่างรายงาน - ระบบบริหารจัดการฐานข้อมูลวัตถุพยาน";

ob_start();
?>

<div class="d-flex align-items-center mb-4">
    <a href="./incidentReportReview.php" class="text-decoration-none text-secondary d-flex align-items-center fw-medium me-3">
        <i class="fa-solid fa-chevron-left me-2" style="margin-top: 2px;"></i> ย้อนกลับ
    </a>
    <div class="vr opacity-25 me-3" style="height: 25px;"></div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="./incidentReportReview.php" class="text-decoration-none text-muted">
                    <i class="fa-solid fa-user-check me-1"></i> การตรวจร่างรายงาน
                </a>
            </li>
            <li class="breadcrumb-item active text-primary" aria-current="page">รายละเอียด</li>
        </ol>
    </nav>
</div>

<div class="page-header d-flex flex-wrap justify-content-between align-items-end pb-3 border-bottom mb-4">
    <div class="d-flex align-items-center">
        <div class="bg-primary rounded-pill me-3 shadow-sm align-self-stretch" style="width: 6px;"></div>
        <div>
            <h3 class="fw-bold mb-0 lh-1 d-flex align-items-baseline flex-wrap gap-2">
                <span class="text-secondary fs-5">เลขที่รายงาน:</span>
                <span class="text-primary"><?= htmlspecialchars($reportNo) ?></span>
            </h3>
            <div class="text-muted small mt-2">
                เลขที่เอกสาร: <?= htmlspecialchars($docNo) ?>
                <span class="mx-2">|</span>
                <?= htmlspecialchars($complaintName) ?>
                <?php if (!empty($incident['province'])): ?>
                    <span class="mx-2">|</span><?= htmlspecialchars($incident['province']) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="mt-3 mt-md-0">
        <span class="badge bg-primary fs-6 px-3 py-2" id="rr_seq_label">รอดำเนินการ</span>
        <span class="badge bg-secondary fs-6 px-3 py-2 ms-1" id="rr_signed_summary">เซ็นแล้ว 0/3</span>
    </div>
</div>

<div id="rr_creator_hint" class="alert alert-warning py-2 px-3 small d-none mb-3">
    <i class="fas fa-info-circle me-1"></i>
    เฉพาะคนสร้างร่างเท่านั้นที่เลือกผู้ตรวจและอัปโหลดไฟล์ได้
</div>

<input type="hidden" id="rr_incident_id" value="<?= (int)$incidentId ?>">
<input type="hidden" id="rr_active_seq" value="">

<div class="row g-3">
    <div class="col-lg-7">
        <fieldset class="mb-0 bg-white p-4 rounded-3 shadow-sm border h-100">
            <legend class="fieldset-header">1. ข้อมูลรายงาน</legend>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-3" style="font-size:14px;">
                    <tbody>
                        <tr>
                            <th class="bg-light" style="width:35%;">เลขที่เอกสาร</th>
                            <td id="rr_doc_no"><?= htmlspecialchars($docNo) ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">เลขที่รายงาน</th>
                            <td id="rr_report_no"><?= htmlspecialchars($reportNo) ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">สภ./สน.</th>
                            <td><?= htmlspecialchars($incident['complaints_From'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">จังหวัด</th>
                            <td><?= htmlspecialchars($incident['province'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">เหตุที่รับแจ้ง</th>
                            <td><?= htmlspecialchars($complaintName) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <label class="form-label small text-muted">อัปโหลดไฟล์รายงาน</label>
            <input type="file" class="form-control" id="rr_file_input" accept=".pdf,.doc,.docx,application/pdf">
            <div class="mt-2" id="rr_file_link_wrap"></div>
        </fieldset>
    </div>

    <div class="col-lg-5">
        <fieldset class="mb-3 bg-white p-4 rounded-3 shadow-sm border">
            <legend class="fieldset-header">2. สถานะการเซ็น</legend>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" style="font-size:14px;">
                    <thead class="table-light text-center">
                        <tr>
                            <th>ลำดับ</th>
                            <th>ชื่อ-สกุล</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="rr_step_1" data-seq="1">
                            <td class="text-center">ผู้ตรวจร่าง 1</td>
                            <td id="rr_sum_name_1">-</td>
                            <td class="text-center"><span class="badge bg-secondary" id="rr_badge_1">ยังไม่เลือก</span></td>
                        </tr>
                        <tr id="rr_step_2" data-seq="2">
                            <td class="text-center">ผู้ตรวจร่าง 2</td>
                            <td id="rr_sum_name_2">-</td>
                            <td class="text-center"><span class="badge bg-secondary" id="rr_badge_2">ยังไม่เลือก</span></td>
                        </tr>
                        <tr id="rr_step_3" data-seq="3">
                            <td class="text-center">ผู้อนุมัติ</td>
                            <td id="rr_sum_name_3">-</td>
                            <td class="text-center"><span class="badge bg-secondary" id="rr_badge_3">ยังไม่เลือก</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </fieldset>

        <fieldset class="mb-0 bg-white p-4 rounded-3 shadow-sm border">
            <legend class="fieldset-header">3. กำหนดผู้ตรวจ</legend>
            <p class="text-muted small mb-3" id="rr_assign_help">เลือกผู้ตรวจทั้ง 3 คน ลงลายเซ็นผู้ร่าง แล้วกดบันทึก</p>
            <div class="mb-3" id="rr_assign_box_1">
                <label class="form-label small text-muted">ผู้ตรวจร่างรายงาน 1</label>
                <select class="form-select rr-user-select" id="rr_reviewer1"></select>
            </div>
            <div class="mb-3" id="rr_assign_box_2">
                <label class="form-label small text-muted">ผู้ตรวจร่างรายงาน 2</label>
                <select class="form-select rr-user-select" id="rr_reviewer2"></select>
            </div>
            <div class="mb-3" id="rr_assign_box_3">
                <label class="form-label small text-muted">ผู้อนุมัติรายงาน</label>
                <select class="form-select rr-user-select" id="rr_approver"></select>
            </div>

            <div class="mb-3" id="rr_drafter_sign_box">
                <label class="form-label small text-muted">ลายเซ็นผู้ร่างรายงาน <span class="text-danger">*</span></label>
                <div class="border rounded bg-white" style="touch-action: none;" id="rr_drafter_canvas_wrap">
                    <canvas id="rr_drafter_canvas" width="1000" height="150" style="width:100%; height:150px;"></canvas>
                </div>
                <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rr_btn_clear_drafter_sig">
                        <i class="fas fa-eraser me-1"></i>ล้างลายเซ็น
                    </button>
                    <span class="text-muted small" id="rr_drafter_sig_hint">ลงลายเซ็นก่อนกดบันทึก</span>
                </div>
                <div class="mt-2 d-none" id="rr_drafter_signed_preview">
                    <label class="form-label small text-muted mb-1">ลายเซ็นผู้ร่างที่บันทึกแล้ว</label>
                    <div>
                        <img id="rr_drafter_signed_img" src="" alt="drafter signature" class="img-fluid border rounded bg-white" style="max-height:100px;">
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm rr-open-sign" data-seq="1"><i class="fas fa-pen me-1"></i>เซ็นลำดับ 1</button>
                <button type="button" class="btn btn-outline-primary btn-sm rr-open-sign" data-seq="2"><i class="fas fa-pen me-1"></i>เซ็นลำดับ 2</button>
                <button type="button" class="btn btn-outline-primary btn-sm rr-open-sign" data-seq="3"><i class="fas fa-pen me-1"></i>เซ็นลำดับ 3</button>
                <button type="button" class="btn btn-warning btn-sm text-dark rr-rollback-btn d-none" id="rr_btn_rollback_top">
                    <i class="fas fa-undo me-1"></i>ย้อนกลับ
                </button>
            </div>
        </fieldset>
    </div>

    <div class="col-12">
        <fieldset class="bg-white p-4 rounded-3 shadow-sm border d-none" id="rr_sign_section">
            <legend class="fieldset-header" id="rr_sign_title">4. ยืนยันผลการตรวจสอบ</legend>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label small text-muted">หมายเหตุ</label>
                    <textarea class="form-control" id="rr_remark" rows="3" placeholder="ระบุหมายเหตุ (ถ้ามี)"></textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label small text-muted">ลายเซ็น</label>
                    <div class="border rounded bg-white" style="touch-action: none;">
                        <canvas id="rr_sig_canvas" width="1000" height="170" style="width:100%; height:170px;"></canvas>
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="rr_btn_clear_sig">
                            <i class="fas fa-eraser me-1"></i>ล้างลายเซ็น
                        </button>
                    </div>
                </div>
                <div class="col-md-12 d-none" id="rr_signed_preview">
                    <label class="form-label small text-muted">ลายเซ็นที่บันทึกแล้ว</label>
                    <div>
                        <img id="rr_signed_img" src="" alt="signature" class="img-fluid border rounded bg-white" style="max-height:110px;">
                    </div>
                </div>
                <div class="col-md-12 text-end">
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="rr_btn_close_sign">ปิดส่วนเซ็น</button>
                    <button type="button" class="btn btn-success" id="rr_btn_confirm_sign">
                        <i class="fas fa-check me-2"></i>ยืนยันผลการตรวจสอบ
                    </button>
                </div>
            </div>
        </fieldset>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4 pb-4">
    <button type="button" class="btn btn-success" id="rr_btn_save_assign">
        <i class="fas fa-save me-2"></i> บันทึก
    </button>
    <a href="./incidentReportReview.php" class="btn btn-danger">
        <i class="fas fa-times me-2"></i> ยกเลิก
    </a>
</div>

<?php
$content = ob_get_clean();
ob_start();
?>
<script>
window._sessionUserId = '<?= (int)$_SESSION["user_id"] ?>';
window._rrUserOptions = <?= json_encode(array_map(function ($u) {
    return ['id' => (int)$u['user_id'], 'name' => trim($u['fullname'])];
}, $userList), JSON_UNESCAPED_UNICODE) ?>;

$(document).ready(function () {
    var rrPad = null;
    var rrDrafterPad = null;
    var rrState = null;
    var incidentId = $('#rr_incident_id').val();

    function esc(v) {
        return $('<div>').text(v == null ? '' : v).html();
    }

    function ajaxFail(xhr, fallback) {
        var msg = fallback || 'เกิดข้อผิดพลาด';
        try {
            var res = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
            if (res && res.message) msg = res.message;
        } catch (e) {}
        Swal.fire('ผิดพลาด', msg, 'error');
    }

    function fillUserSelects() {
        var opts = '<option value="">-- กรุณาเลือก --</option>';
        (window._rrUserOptions || []).forEach(function (u) {
            opts += '<option value="' + u.id + '">' + esc(u.name) + '</option>';
        });
        $('#rr_reviewer1, #rr_reviewer2, #rr_approver').html(opts);
    }

    function initCanvasPad(canvasId, height) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof SignaturePad === 'undefined') return null;
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = height * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        return new SignaturePad(canvas, { backgroundColor: 'rgb(255,255,255)' });
    }

    function initPad() {
        if (rrPad) rrPad.off();
        rrPad = initCanvasPad('rr_sig_canvas', 170);
    }

    function initDrafterPad() {
        if (rrDrafterPad) rrDrafterPad.off();
        rrDrafterPad = initCanvasPad('rr_drafter_canvas', 150);
    }

    function setAssignEnabled(enabled) {
        $('#rr_reviewer1, #rr_reviewer2, #rr_approver, #rr_file_input, #rr_btn_save_assign').prop('disabled', !enabled);
        $('#rr_btn_clear_drafter_sig').prop('disabled', !enabled);
        $('#rr_drafter_canvas_wrap').css('pointer-events', enabled ? 'auto' : 'none');
        $('#rr_drafter_sig_hint').toggleClass('d-none', !enabled);
        $('#rr_creator_hint').toggleClass('d-none', !!enabled);
        if ($.fn.select2) {
            $('#rr_reviewer1, #rr_reviewer2, #rr_approver').trigger('change.select2');
        }
    }

    function applyDrafterSig(approvers, canAssign) {
        var d = approvers && approvers[0] ? approvers[0] : null;
        if (d && d.signature_data) {
            $('#rr_drafter_signed_img').attr('src', d.signature_data);
            $('#rr_drafter_signed_preview').removeClass('d-none');
            if (!canAssign) {
                $('#rr_drafter_canvas_wrap').addClass('d-none');
                $('#rr_btn_clear_drafter_sig').addClass('d-none');
            } else {
                $('#rr_drafter_canvas_wrap').removeClass('d-none');
                $('#rr_btn_clear_drafter_sig').removeClass('d-none');
            }
        } else {
            $('#rr_drafter_signed_preview').addClass('d-none');
            $('#rr_drafter_canvas_wrap').removeClass('d-none');
            $('#rr_btn_clear_drafter_sig').toggleClass('d-none', !canAssign);
        }
    }

    function applySteps(approvers, seqStatus) {
        var signed = 0;
        [1, 2, 3].forEach(function (seq) {
            var a = approvers[seq];
            var $badge = $('#rr_badge_' + seq);
            $badge.removeClass('bg-secondary bg-primary bg-success');
            $('#rr_sum_name_' + seq).text((a && a.fullname) ? a.fullname : '-');

            if (a && a.dateApprove) {
                signed++;
                $badge.addClass('bg-success').text('เซ็นแล้ว');
            } else if (a) {
                if (seqStatus === seq) $badge.addClass('bg-primary').text('ถึงคิวตรวจ');
                else $badge.addClass('bg-secondary').text('รอคิว');
            } else {
                $badge.addClass('bg-secondary').text('ยังไม่เลือก');
            }
        });

        $('#rr_seq_label')
            .removeClass('bg-secondary bg-primary bg-success')
            .addClass(seqStatus >= 4 ? 'bg-success' : 'bg-primary')
            .text(rrState.seq_label || 'รอดำเนินการ');

        var summaryCls = signed >= 3 ? 'bg-success' : (signed > 0 ? 'bg-primary' : 'bg-secondary');
        $('#rr_signed_summary')
            .removeClass('bg-secondary bg-primary bg-success')
            .addClass(summaryCls)
            .text(signed >= 3 ? 'เซ็นครบ 3/3' : ('เซ็นแล้ว ' + signed + '/3'));

        $('#rr_btn_rollback_top').toggleClass('d-none', !rrState.can_rollback);
    }

    function destroySelect2() {
        ['#rr_reviewer1', '#rr_reviewer2', '#rr_approver'].forEach(function (sel) {
            if ($(sel).hasClass('select2-hidden-accessible')) $(sel).select2('destroy');
        });
    }

    function initSelect2() {
        if (!$.fn.select2) return;
        destroySelect2();
        $('#rr_reviewer1, #rr_reviewer2, #rr_approver').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: '-- กรุณาเลือก --',
            allowClear: true
        });
    }

    function loadData() {
        destroySelect2();
        fillUserSelects();
        $('#rr_sign_section').addClass('d-none');
        $('#rr_file_input').val('');

        $.ajax({
            url: '/csims/api/reportReview/getData.php',
            type: 'GET',
            data: { incident_id: incidentId },
            dataType: 'json'
        }).done(function (res) {
            if (res.status !== 'success') {
                Swal.fire('ผิดพลาด', res.message || 'โหลดข้อมูลไม่สำเร็จ', 'error');
                return;
            }
            rrState = res;
            var inc = res.incident;

            if (res.approvers[1]) $('#rr_reviewer1').val(String(res.approvers[1].user_id));
            if (res.approvers[2]) $('#rr_reviewer2').val(String(res.approvers[2].user_id));
            if (res.approvers[3]) $('#rr_approver').val(String(res.approvers[3].user_id));

            setAssignEnabled(!!res.can_assign);
            $('#rr_file_input').prop('disabled', !res.can_upload);
            applyDrafterSig(res.approvers, !!res.can_assign);

            if (parseInt(inc.has_file, 10) === 1) {
                $('#rr_file_link_wrap').html(
                    '<a class="btn btn-sm btn-outline-secondary" href="/csims/api/reportReview/downloadFile.php?incident_id=' +
                    incidentId + '" target="_blank"><i class="fas fa-file-alt me-1"></i>เปิดไฟล์รายงาน</a>'
                );
            } else {
                $('#rr_file_link_wrap').html('<span class="text-muted small">ยังไม่มีไฟล์</span>');
            }

            applySteps(res.approvers, parseInt(inc.seqStatusApprove, 10) || 1);
            setTimeout(function () {
                initSelect2();
                initPad();
                initDrafterPad();
            }, 100);
        }).fail(function (xhr) {
            ajaxFail(xhr, 'โหลดข้อมูลไม่สำเร็จ');
        });
    }

    function openSignPanel(seq) {
        if (!rrState) return;
        seq = parseInt(seq, 10);
        var a = rrState.approvers[seq];
        if (!a) {
            Swal.fire('แจ้งเตือน', 'ยังไม่ได้บันทึกผู้ตรวจลำดับนี้', 'warning');
            return;
        }

        $('#rr_active_seq').val(seq);
        var titles = {
            1: '4. ยืนยันผลการตรวจสอบ — ผู้ตรวจร่างรายงาน 1',
            2: '4. ยืนยันผลการตรวจสอบ — ผู้ตรวจร่างรายงาน 2',
            3: '4. ยืนยันผลการตรวจสอบ — ผู้อนุมัติรายงาน'
        };
        $('#rr_sign_title').text(titles[seq]);
        $('#rr_sign_section').removeClass('d-none');
        $('#rr_remark').val(a.remark || '');

        var canApprove = parseInt(rrState.can_approve_seq, 10) === seq;
        var signed = !!a.dateApprove;

        $('#rr_btn_confirm_sign').prop('disabled', !canApprove).toggleClass('d-none', !!signed);
        $('#rr_btn_clear_sig').prop('disabled', !canApprove || !!signed).toggleClass('d-none', !!signed);
        $('#rr_remark').prop('readonly', !canApprove || !!signed);

        if (!canApprove && !signed) {
            Swal.fire({
                toast: true, position: 'top-end', icon: 'info',
                title: 'ยังไม่ถึงคิวของคุณ หรือคุณไม่ใช่ผู้ตรวจลำดับนี้',
                showConfirmButton: false, timer: 2500
            });
        }

        if (signed && a.signature_data) {
            $('#rr_signed_img').attr('src', a.signature_data);
            $('#rr_signed_preview').removeClass('d-none');
            if (rrPad) rrPad.off();
        } else {
            $('#rr_signed_preview').addClass('d-none');
            setTimeout(function () {
                initPad();
                if (rrPad && !canApprove) rrPad.off();
            }, 80);
        }

        document.getElementById('rr_sign_section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    $(document).on('click', '.rr-open-sign', function () {
        openSignPanel($(this).data('seq'));
    });

    $('#rr_btn_close_sign').on('click', function () {
        $('#rr_sign_section').addClass('d-none');
    });
    $('#rr_btn_clear_sig').on('click', function () {
        if (rrPad) rrPad.clear();
    });
    $('#rr_btn_clear_drafter_sig').on('click', function () {
        if (rrDrafterPad) rrDrafterPad.clear();
    });

    $('#rr_btn_save_assign').on('click', function () {
        var payload = {
            incident_id: incidentId,
            reviewer1_id: $('#rr_reviewer1').val() || '',
            reviewer2_id: $('#rr_reviewer2').val() || '',
            approver_id: $('#rr_approver').val() || ''
        };
        if (!payload.reviewer1_id || !payload.reviewer2_id || !payload.approver_id) {
            Swal.fire('แจ้งเตือน', 'กรุณาเลือกผู้ตรวจครบทั้ง 3 คน', 'warning');
            return;
        }
        if (payload.reviewer1_id === payload.reviewer2_id ||
            payload.reviewer1_id === payload.approver_id ||
            payload.reviewer2_id === payload.approver_id) {
            Swal.fire('แจ้งเตือน', 'ผู้ตรวจแต่ละลำดับต้องเป็นคนละคน', 'warning');
            return;
        }
        if (!rrDrafterPad || rrDrafterPad.isEmpty()) {
            Swal.fire('แจ้งเตือน', 'กรุณาลงลายเซ็นผู้ร่างรายงาน', 'warning');
            return;
        }
        payload.signature = rrDrafterPad.toDataURL('image/png');

        $.ajax({
            url: '/csims/api/reportReview/assignReviewers.php',
            type: 'POST',
            data: payload,
            dataType: 'json'
        }).done(function (res) {
            if (res.status !== 'success') {
                Swal.fire('ผิดพลาด', res.message || 'บันทึกไม่สำเร็จ', 'error');
                return;
            }
            var file = $('#rr_file_input')[0].files[0];
            if (!file) {
                Swal.fire('สำเร็จ', res.message || 'บันทึกเรียบร้อย', 'success');
                loadData();
                return;
            }
            var fd = new FormData();
            fd.append('incident_id', incidentId);
            fd.append('report_file', file);
            $.ajax({
                url: '/csims/api/reportReview/uploadFile.php',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function (up) {
                if (up.status !== 'success') {
                    Swal.fire('บันทึกผู้ตรวจแล้ว แต่ไฟล์ไม่สำเร็จ', up.message || '', 'warning');
                } else {
                    Swal.fire('สำเร็จ', 'บันทึกเรียบร้อย', 'success');
                }
                loadData();
            }).fail(function (xhr) { ajaxFail(xhr, 'อัปโหลดไฟล์ไม่สำเร็จ'); });
        }).fail(function (xhr) { ajaxFail(xhr, 'บันทึกผู้ตรวจไม่สำเร็จ'); });
    });

    $('#rr_btn_confirm_sign').on('click', function () {
        if (!rrPad || rrPad.isEmpty()) {
            Swal.fire('แจ้งเตือน', 'กรุณาลงลายเซ็น', 'warning');
            return;
        }
        $.ajax({
            url: '/csims/api/reportReview/approve.php',
            type: 'POST',
            data: {
                incident_id: incidentId,
                seq_no: $('#rr_active_seq').val(),
                remark: $('#rr_remark').val(),
                signature: rrPad.toDataURL('image/png')
            },
            dataType: 'json'
        }).done(function (res) {
            if (res.status !== 'success') {
                Swal.fire('ผิดพลาด', res.message || 'บันทึกไม่สำเร็จ', 'error');
                return;
            }
            Swal.fire('สำเร็จ', res.message, 'success');
            loadData();
        }).fail(function (xhr) { ajaxFail(xhr, 'ยืนยันไม่สำเร็จ'); });
    });

    $(document).on('click', '.rr-rollback-btn', function () {
        Swal.fire({
            title: 'ย้อนกลับ?',
            text: 'จะเคลียร์ลายเซ็น/หมายเหตุ/สถานะของผู้ตรวจ 1–3 (คงลายเซ็นผู้ร่างไว้)',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ยืนยันย้อนกลับ',
            cancelButtonText: 'ยกเลิก'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: '/csims/api/reportReview/rollback.php',
                type: 'POST',
                data: { incident_id: incidentId },
                dataType: 'json'
            }).done(function (res) {
                if (res.status !== 'success') {
                    Swal.fire('ผิดพลาด', res.message || 'ย้อนกลับไม่สำเร็จ', 'error');
                    return;
                }
                Swal.fire('สำเร็จ', res.message, 'success');
                loadData();
            }).fail(function (xhr) { ajaxFail(xhr, 'ย้อนกลับไม่สำเร็จ'); });
        });
    });

    loadData();
});
</script>
<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
