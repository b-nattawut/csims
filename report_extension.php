<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require __DIR__ . '/helpers/report_no.php';

// นับข้อมูลที่มีรายงานแล้ว (incident_report_data IS NOT NULL)
$qryCount = "SELECT COUNT(*) as countData
    FROM rn_ReceiveNoti t1
    LEFT JOIN users t2 ON t1.create_by = t2.user_id
    LEFT JOIN incident_checklist_transaction ict ON ict.incident_id = t1.id
    WHERE t1.statusDelete = 0 AND t2.is_active = 1 AND ict.incident_report_data IS NOT NULL";
$stmt = $pdo->prepare($qryCount);
$stmt->execute();
$countData = $stmt->fetchColumn();

$limit = 15;
$total_pages = ceil($countData / $limit);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$title = "ขอขยายเวลาการออกรายงาน - ระบบบริหารจัดการฐานข้อมูลวัตถุพยาน";

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
                    <div class="row g-3 justify-content-center align-items-end">

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">เลขที่เอกสาร</label>
                            <input type="text" class="form-control form-control-sm" id="filter_doc_no" name="filter_doc_no">
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">เลขที่รายงาน</label>
                            <input type="text" class="form-control form-control-sm" id="filter_report_no" name="filter_report_no">
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">เหตุที่รับแจ้ง</label>
                            <select class="form-select form-select-sm" id="filter_complaint" name="filter_complaint">
                                <option value="">ทั้งหมด</option>
                                <option value="01">คดีทรัพย์</option>
                                <option value="02">คดีชีวิต</option>
                                <option value="03">คดีระเบิด</option>
                                <option value="04">คดีเพลิงไหม้</option>
                                <option value="05">คดีจราจร</option>
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
                จำนวนข้อมูลทั้งหมด : <span id="count_display"><?= $countData ?></span> รายการ
            </div>
        </div>

        <div class="table-responsive">
            <div class="table-wrapper-focus">
                <table class="table table-bordered table-striped table-hover table-custom align-middle mb-0">
                    <thead style="font-size: 14px;">
                        <tr class="text-nowrap text-center">
                            <th style="width: 5%">ลำดับ</th>
                            <th style="width: 18%">เลขที่เอกสาร</th>
                            <th style="width: 18%">เลขที่รายงาน</th>
                            <th style="width: 30%">เหตุที่รับแจ้ง</th>
                            <th style="width: 14%">ดาวน์โหลดเอกสาร</th>
                        </tr>
                    </thead>
                    <tbody id="table_body" style="font-size: 14px;">
                        <?php
                        $qryDataTable = "SELECT t1.id, t1.receiveNoti_No, t1.receiveNotiReportNo, t1.receiveNoti_No_TH, t1.receiveNotiReportNo_TH, t1.create_by,
                                t1.complaints_type, t1.complaints_From,
                                CASE WHEN t1.complaints_type = '01' THEN 'ทรัพย์'
                                     WHEN t1.complaints_type = '02' THEN 'ชีวิต'
                                     WHEN t1.complaints_type = '03' THEN 'ระเบิด'
                                     WHEN t1.complaints_type = '04' THEN 'เพลิงไหม้'
                                     WHEN t1.complaints_type = '05' THEN 'จราจร'
                                     WHEN t1.complaints_type = '06' THEN 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)'
                                     WHEN t1.complaints_type = '07' THEN 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ'
                                     WHEN t1.complaints_type = '08' THEN 'ตรวจเก็บวัตถุพยานบุคคล'
                                     ELSE CONCAT('ไม่ทราบ (', t1.complaints_type, ')') END AS complaintstype,
                                CASE WHEN t1.complaints_type IN ('02','03') THEN ict.incident_checklist_data ELSE NULL END AS incident_checklist_data,
                                CASE WHEN ict.incident_extend_time_data IS NOT NULL AND ict.incident_extend_time_data != '' THEN 1 ELSE 0 END AS has_extension_data
                                FROM rn_ReceiveNoti t1
                                LEFT JOIN users t2 ON t1.create_by = t2.user_id
                                LEFT JOIN incident_checklist_transaction ict ON ict.incident_id = t1.id
                                WHERE t1.statusDelete = 0 AND t2.is_active = 1 AND ict.incident_report_data IS NOT NULL
                                ORDER BY t1.id DESC
                                LIMIT :limit OFFSET :offset";
                        $offset = ($page - 1) * $limit;
                        $stmt = $pdo->prepare($qryDataTable);
                        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                        // echo $qryDataTable;
                        $stmt->execute();
                        if ($stmt->rowCount() > 0) {
                            $index = $offset + 1;
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                // ตรวจสอบ location_type สำหรับแสดงผล
                                $complaintsDisplay = $row['complaintstype'];
                                if ($row['complaints_type'] == '02' && !empty($row['incident_checklist_data'])) {
                                    $ckData = json_decode($row['incident_checklist_data'], true);
                                    $sc = $ckData['scene_characteristics'] ?? [];
                                    if (!empty($sc['has_outdoor'])) {
                                        $complaintsDisplay = 'ชีวิต (นอกอาคาร)';
                                    } elseif (!empty($sc['has_indoor'])) {
                                        $complaintsDisplay = 'ชีวิต (ในอาคาร)';
                                    }
                                } elseif ($row['complaints_type'] == '03' && !empty($row['incident_checklist_data'])) {
                                    $ckData = json_decode($row['incident_checklist_data'], true);
                                    $sc = $ckData['scene_info'] ?? [];
                                    $indoor = ($sc['indoor']['is_active'] ?? 0) == 1;
                                    $complaintsDisplay = $indoor ? 'ระเบิด (ในอาคาร)' : 'ระเบิด (นอกอาคาร)';
                                }

                                $hasExtData = (int)$row['has_extension_data'] === 1;
                        ?>
                                <tr class="rex-row" style="cursor: pointer;" data-id="<?= $row['id']; ?>" data-create-by="<?= $row['create_by']; ?>">
                                    <td class="text-center"><?= $index++; ?></td>
                                    <td class="text-center"><?= htmlspecialchars(!empty($row['receiveNoti_No_TH']) ? $row['receiveNoti_No_TH'] : convertDocNoToThai($row['receiveNoti_No'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center"><?= htmlspecialchars(!empty($row['receiveNotiReportNo_TH']) ? $row['receiveNotiReportNo_TH'] : convertReportNoToThai($row['receiveNotiReportNo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($complaintsDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center">
                                        <?php if ($hasExtData): ?>
                                            <button type="button" class="btn btn-sm btn-download-ext" style="background:#7c3aed; box-shadow:0 2px 6px rgba(124,58,237,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-dl-id="<?= $row['id']; ?>" title="ดาวน์โหลด">
                                                <i class="fas fa-download me-1"></i>ดาวน์โหลด
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                                                <i class="fas fa-download me-1"></i>ดาวน์โหลด
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                        ?>
                            <tr><td colspan="5" class="text-center">ไม่พบข้อมูล</td></tr>
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

<!-- Modal -->
<?php include 'modals/modal_report_extension.php'; ?>

<?php
$content = ob_get_clean();
ob_start();
?>

<script src="js/report_no_th.js"></script>
<script>
    window._sessionUserId = '<?= $_SESSION["user_id"] ?>';
$(document).ready(function() {

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
            url: '/csims/api/incidentCheckList/searchReportExtension.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status == 'success') {
                    console.log(response.data,"<--------response.data");
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
                let complaintsDisplay = row.complaintstype || '';
                let hasExtData = parseInt(row.has_extension_data) === 1;

                let downloadBtn = '';
                if (hasExtData) {
                    downloadBtn = '<button type="button" class="btn btn-sm btn-download-ext" style="background:#7c3aed; box-shadow:0 2px 6px rgba(124,58,237,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-dl-id="' + row.id + '" title="ดาวน์โหลด"><i class="fas fa-download me-1"></i>ดาวน์โหลด</button>';
                } else {
                    downloadBtn = '<button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล"><i class="fas fa-download me-1"></i>ดาวน์โหลด</button>';
                }

                html += `
                    <tr class="rex-row" style="cursor: pointer;" data-id="${row.id}" data-create-by="${row.create_by || ''}">
                        <td class="text-center">${index}</td>
                        <td class="text-center">${row.receiveNoti_No_TH || (window.toThaiDocNo ? toThaiDocNo(row.receiveNoti_No) : row.receiveNoti_No) || ''}</td>
                        <td class="text-center">${row.receiveNotiReportNo_TH || (window.toThaiReportNo ? toThaiReportNo(row.receiveNotiReportNo) : row.receiveNotiReportNo) || ''}</td>
                        <td>${complaintsDisplay}</td>
                        <td class="text-center">${downloadBtn}</td>
                    </tr>
                `;
                index++;
            });
        } else {
            html = '<tr><td colspan="5" class="text-center">ไม่พบข้อมูล</td></tr>';
        }
        $('#table_body').html(html);
    }

    // --- Setup Pagination ---
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
        }, 100);
    }

    // --- Row Click => Open Modal ---
    $(document).on('click', '.rex-row', function(e) {
        // ถ้าคลิกที่ปุ่ม download หรือ disabled button ไม่ต้องเปิด modal
        if ($(e.target).closest('.btn-download-ext, .btn-no-action').length) {
            return;
        }

        const incidentId = $(this).data('id');
        const createBy = String($(this).data('create-by') || '');
        openReportExtensionModal(incidentId, createBy);
    });


    // --- Open Report Extension Modal ---
    function openReportExtensionModal(incidentId, createBy) {
        // Reset form
        document.getElementById('formReportExtension').reset();
        $('#rex_incident_id').val(incidentId);

        // ★ Permission check: only creator can save
        var btnSave = document.getElementById('btn_save_report_extension');
        if (btnSave) {
            if (createBy && String(createBy) !== String(window._sessionUserId)) {
                btnSave.disabled = true;
                btnSave.title = 'เฉพาะผู้สร้างรายการเท่านั้นที่สามารถบันทึกได้';
            } else {
                btnSave.disabled = false;
                btnSave.title = '';
            }
        }

        // Load existing data
        $.ajax({
            url: '/csims/api/incidentCheckList/getReportExtensionData.php',
            type: 'GET',
            data: { incident_id: incidentId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && Object.keys(response.data).length > 0) {
                    // มีข้อมูลขอขยายเวลาแล้ว => ใช้ข้อมูลนั้น
                    prefillExtensionForm(response.data);
                } else if (response.report_data) {
                    // ยังไม่มีข้อมูลขอขยายเวลา => ดึงจาก incident_report_data มา prefill
                    prefillFromReportData(response.report_data);
                }

                // ดึงข้อมูลสถานที่เกิดเหตุ + ประเภทเหตุ
                if (response.incident_info) {
                    var typeName = response.incident_info.complaints_type_name || '';
                    var location = response.incident_info.incident_location || '';
                    var reportNo = response.incident_info.report_no || '';

                    // แสดงประเภทเหตุเป็น text
                    $('#rex_complaints_type_display').text(typeName);

                    // แสดงเลขรายงาน
                    $('#rex_report_no_display').text(reportNo);

                    // ใส่สถานที่เข้า textarea (ถ้ายังว่าง)
                    if (!$('#rex_case_about').val() && location) {
                        $('#rex_case_about').val(location);
                    }
                }

                showExtensionModal();
            },
            error: function() {
                showExtensionModal();
            }
        });
    }

    // --- Show Modal (reuse existing instance) ---
    function showExtensionModal() {
        const modalEl = document.getElementById('modalReportExtension');
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }
        modal.show();
    }

    // --- Prefill from incident_report_data ---
    function prefillFromReportData(reportData) {
        if (!reportData) return;

        // หา report type และ data ที่ถูกต้อง
        const reportType = reportData.report_type || '';
        let formData = null;

        // ดึงข้อมูลตาม report_type
        if (reportType === 'property' && reportData.property) {
            formData = reportData.property;
            prefillMapped(formData, 'rp_');
        } else if (reportType === 'indoor' && reportData.indoor) {
            formData = reportData.indoor;
            prefillMapped(formData, 'rli_');
        } else if (reportType === 'outdoor' && reportData.outdoor) {
            formData = reportData.outdoor;
            prefillMapped(formData, 'rlo_');
        } else if (reportType === 'fire' && reportData.fire) {
            formData = reportData.fire;
            prefillMapped(formData, 'rf_');
        } else {
            // ลองหา data จาก key แรกที่มี
            for (const key of ['property','indoor','outdoor','fire']) {
                if (reportData[key]) {
                    formData = reportData[key];
                    const prefixMap = { property: 'rp_', indoor: 'rli_', outdoor: 'rlo_', fire: 'rf_' };
                    prefillMapped(formData, prefixMap[key]);
                    break;
                }
            }
        }
    }

    // --- Map report fields to extension form ---
    function prefillMapped(data, prefix) {
        if (!data) return;

        // Case behavior → case about
        if (data[prefix + 'case_behavior']) {
            $('#rex_case_about').val(data[prefix + 'case_behavior']);
        }

        // Signer name → requester name (select)
        if (data[prefix + 'signer_name']) {
            $('#rex_requester_name').val(data[prefix + 'signer_name']);
        }
    }

    // --- Prefill Extension Form ---
    function prefillExtensionForm(data) {
        if (!data) return;
        const form = document.getElementById('formReportExtension');

        Object.keys(data).forEach(function(key) {
            const val = data[key];

            // ฟิลด์ display (ไม่ใช่ input)
            if (key === 'rex_complaints_type') {
                $('#rex_complaints_type_display').text(val);
                return;
            }
            if (key === 'rex_report_no') {
                $('#rex_report_no_display').text(val);
                return;
            }

            const fields = form.querySelectorAll('[name="' + key + '"]');

            if (fields.length === 0) return;

            fields.forEach(function(field) {
                if (field.type === 'checkbox') {
                    if (Array.isArray(val)) {
                        field.checked = val.includes(field.value);
                    } else {
                        field.checked = (field.value === val);
                    }
                } else if (field.type === 'radio') {
                    field.checked = (field.value === val);
                } else {
                    field.value = val;
                }
            });
        });
    }

    // --- Save Report Extension ---
    $('#btn_save_report_extension').on('click', function() {
        const incidentId = $('#rex_incident_id').val();
        if (!incidentId) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
            return;
        }

        const formData = {};
        const formEl = document.getElementById('formReportExtension');
        const elements = formEl.elements;

        for (let i = 0; i < elements.length; i++) {
            const el = elements[i];
            if (!el.name || el.name === 'rex_incident_id') continue;

            if (el.type === 'checkbox') {
                if (!formData[el.name]) formData[el.name] = [];
                if (el.checked) formData[el.name].push(el.value);
            } else if (el.type === 'radio') {
                if (el.checked) formData[el.name] = el.value;
            } else if (el.tagName === 'SELECT') {
                formData[el.name] = el.value;
            } else {
                formData[el.name] = el.value;
            }
        }

        // เก็บข้อมูล display (ไม่ใช่ input แต่ต้องการบันทึก)
        formData['rex_complaints_type'] = $('#rex_complaints_type_display').text() || '';
        formData['rex_report_no'] = $('#rex_report_no_display').text() || '';

        const payload = {
            incident_id: incidentId,
            form_data: formData
        };

        const btn = this;
        $.ajax({
            url: '/csims/api/incidentCheckList/saveReportExtension.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            beforeSend: function() {
                $(btn).prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: response.message,
                        confirmButtonColor: '#7c5cbf',
                        confirmButtonText: 'ตกลง'
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
            },
            complete: function() {
                $(btn).prop('disabled', false);
            }
        });
    });

    // --- Download button click => ดาวน์โหลด DOCX ---
    $(document).on('click', '.btn-download-ext', function(e) {
        e.stopPropagation();
        const incidentId = $(this).data('dl-id');
        window.location.href = '/csims/api/incidentCheckList/downloadReportExtensionDocx.php?incident_id=' + incidentId;
    });

    // --- เลือกวันที่เริ่ม-สิ้นสุด => คำนวณจำนวนวันอัตโนมัติ ---
    $(document).on('change', '#rex_approve_start_date, #rex_approve_end_date', function() {
        const start = $('#rex_approve_start_date').val();
        const end = $('#rex_approve_end_date').val();
        if (start && end) {
            const diffMs = new Date(end) - new Date(start);
            const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));
            if (diffDays >= 0) {
                $('#rex_approve_days').val(diffDays);
            }
        }
    });

});
</script>

<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>
