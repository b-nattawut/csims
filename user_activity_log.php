<?php
session_start();
// ตรวจสอบว่า login หรือยัง
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db_config.php';

$currentUserStmt = $pdo->prepare("SELECT role, is_active FROM users WHERE user_id = ? LIMIT 1");
$currentUserStmt->execute([$_SESSION['user_id']]);
$currentUser = $currentUserStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser || (int)($currentUser['is_active'] ?? 0) !== 1) {
    session_unset();
    session_destroy();
    header('Location: login.html');
    exit;
}

$_SESSION['role'] = $currentUser['role'];

if (($currentUser['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// กำหนด Title ของหน้า
$title = "ประวัติการใช้งาน";

// ตัวกรอง
$search_name = trim($_GET['search_name'] ?? '');
$search_menu = trim($_GET['search_menu'] ?? '');
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$date_from = trim($_GET['date_from'] ?? $current_month_start);
$date_to = trim($_GET['date_to'] ?? $current_month_end);
$export = isset($_GET['export']) && $_GET['export'] === '1';
$allowed_per_page = [10, 15];
$per_page = (int)($_GET['per_page'] ?? 15);
if (!in_array($per_page, $allowed_per_page, true)) {
    $per_page = 15;
}
$page = max(1, (int)($_GET['page'] ?? 1));

// ดึงรายการเมนูสำหรับ dropdown
$menu_options = [];
try {
    $stmtMenus = $pdo->query("SELECT DISTINCT name_menu FROM log_user_usage WHERE name_menu IS NOT NULL AND name_menu <> '' ORDER BY name_menu ASC");
    $menu_options = $stmtMenus->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $menu_options = [];
}

try {
    $baseSql = "
        FROM log_user_usage l
        LEFT JOIN users u ON u.user_id = l.user_id
        LEFT JOIN user_profile p ON p.user_id = l.user_id
        LEFT JOIN user_rank r ON r.rank_id = p.rank_id
        WHERE 1=1
    ";

    $params = [];

    if ($search_name !== '') {
        $baseSql .= " AND (TRIM(CONCAT(IFNULL(r.rank_name, ''), ' ', IFNULL(p.first_name, ''), ' ', IFNULL(p.last_name, ''))) LIKE :search_name OR u.email LIKE :search_name) ";
        $params[':search_name'] = '%' . $search_name . '%';
    }

    if ($search_menu !== '') {
        $baseSql .= " AND l.name_menu = :search_menu ";
        $params[':search_menu'] = $search_menu;
    }

    if ($date_from !== '') {
        $baseSql .= " AND DATE(l.date_use_page) >= :date_from ";
        $params[':date_from'] = $date_from;
    }

    if ($date_to !== '') {
        $baseSql .= " AND DATE(l.date_use_page) <= :date_to ";
        $params[':date_to'] = $date_to;
    }

    $countSql = "SELECT COUNT(l.id) " . $baseSql;
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $countData = (int)$stmtCount->fetchColumn();
    $total_pages = max(1, (int)ceil($countData / $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $offset = ($page - 1) * $per_page;

    $selectSql = "
        SELECT
            l.id,
            l.user_id,
            l.url,
            l.name_menu,
            l.date_use_page,
            l.total_time_use,
            DATE_ADD(l.date_use_page, INTERVAL l.total_time_use SECOND) AS last_activity_time,
            u.email,
            TRIM(CONCAT(IFNULL(r.rank_name, ''), ' ', IFNULL(p.first_name, ''), ' ', IFNULL(p.last_name, ''))) AS full_name
    " . $baseSql . " ORDER BY l.date_use_page DESC ";

    if (!$export) {
        $selectSql .= " LIMIT :limit OFFSET :offset";
    }

    $stmt = $pdo->prepare($selectSql);
    if (!$export) {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt->execute($params);
    }
    $activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activity_logs = [];
    $countData = 0;
    $total_pages = 1;
    $page = 1;
    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

if ($export) {
    require_once __DIR__ . '/vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ประวัติการใช้งาน');

    // ซ่อนคอลัมน์ URL ชั่วคราว
    // $headers = ['ลำดับ', 'ชื่อผู้ใช้งาน', 'อีเมล', 'เมนู', 'URL', 'เวลาเข้าใช้', 'เวลาที่ใช้งาน', 'เข้าใช้ล่าสุด'];
    $headers = ['ลำดับ', 'ชื่อผู้ใช้งาน', 'อีเมล', 'เมนู', 'เวลาเข้าใช้', 'เวลาที่ใช้งาน', 'เข้าใช้ล่าสุด'];
    $sheet->fromArray($headers, null, 'A1');

    $sheet->getStyle('A1:G1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 11,
            'color' => ['rgb' => '000000'],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9D9D9'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'BFBFBF'],
            ],
        ],
    ]);

    $sheet->getRowDimension(1)->setRowHeight(20);

    $rowNumber = 2;
    $index = 1;
    foreach ($activity_logs as $row) {
        $displayName = trim($row['full_name']) !== '' ? trim($row['full_name']) : ('User #' . $row['user_id']);
        $seconds = (float)$row['total_time_use'];
        $durationHours = floor($seconds / 3600);
        $durationMinutes = floor(($seconds % 3600) / 60);
        $durationSeconds = floor($seconds % 60);
        $durationText = sprintf('%d ชั่วโมง %d นาที %d วินาที', $durationHours, $durationMinutes, $durationSeconds);

        $sheet->fromArray([
            $index++,
            $displayName,
            $row['email'] ?? '',
            $row['name_menu'],
            // $row['url'], // ซ่อน URL ชั่วคราว
            $row['date_use_page'],
            $durationText,
            $row['last_activity_time'],
        ], null, 'A' . $rowNumber);
        $rowNumber++;
    }

    if ($rowNumber > 2) {
        $sheet->getStyle('A2:G' . ($rowNumber - 1))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        // จัดกึ่งกลางเฉพาะคอลัมน์ลำดับ
        $sheet->getStyle('A2:A' . ($rowNumber - 1))->getAlignment()->setHorizontal(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        );
    }

    foreach (range('A', 'G') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $sheet->freezePane('A2');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="user_activity_log_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

ob_start();
?>

<style>
    .table-custom thead th {
        background-color: #3468eb;
        color: #fff;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }

    .table-custom tbody td {
        vertical-align: middle;
        font-size: 13px;
    }

    .duration-text {
        font-size: 13px;
        color: #212529;
        font-weight: 400;
    }

    /* ซ่อนการใช้งาน URL ชั่วคราว */
    /* .text-url {
        max-width: 260px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: inline-block;
    } */

    .pagination .page-link {
        font-size: 13px;
    }

    .pagination-wrap {
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 2px;
    }

    .pagination-wrap .pagination {
        flex-wrap: nowrap;
        white-space: nowrap;
    }
</style>

<div class="container-fluid">
    <div class="card mb-3 shadow-sm" style="font-size:13px;">
        <div class="card-body p-2">
            <div class="d-flex flex-wrap justify-content-end align-items-center mb-2">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterSection" aria-expanded="true">
                    <i class="fas fa-filter me-1"></i> ตัวกรอง
                </button>
            </div>

            <div class="collapse show" id="filterSection">
                <div class="bg-light p-3 rounded-3 border mb-2 shadow-sm">
                    <form method="GET" id="searchFilterForm">
                        <div class="row g-3 justify-content-center align-items-end">
                            <div class="col-md-4 col-sm-12">
                                <label class="form-label mb-0 text-muted" style="font-size: 11px;">ชื่อผู้ใช้งาน</label>
                                <input type="text" class="form-control form-control-sm" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>" >
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <label class="form-label mb-0 text-muted" style="font-size: 11px;">เมนู</label>
                                <select class="form-select form-select-sm" name="search_menu">
                                    <option value="">ทั้งหมด</option>
                                    <?php foreach ($menu_options as $menu): ?>
                                        <option value="<?php echo htmlspecialchars($menu); ?>" <?php echo $search_menu === $menu ? 'selected' : ''; ?>><?php echo htmlspecialchars($menu); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <label class="form-label mb-0 text-muted" style="font-size: 11px;">วันที่เริ่ม</label>
                                <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <label class="form-label mb-0 text-muted" style="font-size: 11px;">วันที่สิ้นสุด</label>
                                <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>

                            

                            <div class="col-md-12 d-flex justify-content-center align-items-center gap-2 mt-3">
                                <a href="user_activity_log.php" class="btn btn-warning btn-sm text-dark fw-bold px-3">
                                    <i class="fas fa-undo me-1"></i> ล้างค่า
                                </a>
                                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                                    <i class="fas fa-search me-1"></i> ค้นหา
                                </button>
                                <button type="submit" name="export" value="1" class="btn btn-success btn-sm px-3 fw-bold">
                                    <i class="fas fa-file-excel me-1"></i> Export
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
            <div class="d-flex justify-content-end align-items-center mb-3">
                <div class="text-muted small">
                    จำนวนข้อมูลทั้งหมด : <span id="count_display"><?php echo $countData; ?></span> รายการ
                </div>
            </div>

            <?php if (!empty($error ?? '')): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered table-hover table-custom align-middle mb-0 table-striped">
                    <thead style="font-size: 14px;">
                        <tr>
                            <th style="width: 4%;">ลำดับ</th>
                            <th style="width: 18%;">ชื่อผู้ใช้งาน</th>
                            <th style="width: 18%;">อีเมล</th>
                            <th style="width: 14%;">เมนู</th>
                            <th style="width: 18%;">เวลาเข้าใช้</th>
                            <th style="width: 14%;">เวลาที่ใช้งานล่าสุด</th>
                            <th style="width: 14%;">เข้าใช้งานล่าสุด</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 13px;">
                        <?php if (!empty($activity_logs)): ?>
                            <?php $index = $offset + 1; ?>
                            <?php foreach ($activity_logs as $log): ?>
                                <?php
                                $displayName = trim($log['full_name']) !== '' ? trim($log['full_name']) : ('User #' . $log['user_id']);
                                $seconds = (float)$log['total_time_use'];
                                $durationHours = floor($seconds / 3600);
                                $durationMinutes = floor(($seconds % 3600) / 60);
                                $durationSeconds = floor($seconds % 60);
                                $durationText = sprintf('%d ชั่วโมง %d นาที %d วินาที', $durationHours, $durationMinutes, $durationSeconds);
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $index++; ?></td>
                                    <td><?php echo htmlspecialchars($displayName); ?></td>
                                    <td><?php echo htmlspecialchars($log['email'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($log['name_menu']); ?></td>
                                    <!-- <td><span class="text-url" title="<?php echo htmlspecialchars($log['url']); ?>"><?php echo htmlspecialchars($log['url']); ?></span></td> -->
                                    <td class="text-center"><?php echo date('d/m/Y H:i:s', strtotime($log['date_use_page'])); ?></td>
                                    <td class="text-center"><span class="duration-text"><?php echo $durationText; ?></span></td>
                                    <td class="text-center"><?php echo date('d/m/Y H:i:s', strtotime($log['last_activity_time'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 opacity-50"></i>
                                    <div>ไม่พบข้อมูลการใช้งาน</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <?php
                $query_params = $_GET;
                unset($query_params['page'], $query_params['export']);

                $build_page_url = function($targetPage) use ($query_params) {
                    return 'user_activity_log.php?' . http_build_query(array_merge($query_params, ['page' => $targetPage]));
                };

                $max_visible_pages = 7;
                $half = (int)floor($max_visible_pages / 2);
                $start_page = max(1, $page - $half);
                $end_page = min($total_pages, $start_page + $max_visible_pages - 1);
                $start_page = max(1, $end_page - $max_visible_pages + 1);
                ?>
                <div class="d-flex justify-content-center mt-3">
                    <nav aria-label="Pagination" class="pagination-wrap">
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars($build_page_url(max(1, $page - 1))); ?>" aria-label="Previous">&laquo;</a>
                            </li>

                            <?php if ($start_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo htmlspecialchars($build_page_url(1)); ?>">1</a>
                                </li>
                                <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($pageNum = $start_page; $pageNum <= $end_page; $pageNum++): ?>
                                <li class="page-item <?php echo $pageNum === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars($build_page_url($pageNum)); ?>"><?php echo $pageNum; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < ($total_pages - 1)): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo htmlspecialchars($build_page_url($total_pages)); ?>"><?php echo $total_pages; ?></a>
                                </li>
                            <?php endif; ?>

                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars($build_page_url(min($total_pages, $page + 1))); ?>" aria-label="Next">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

   
<?php
$content = ob_get_clean();
include 'layout.php';
?>
