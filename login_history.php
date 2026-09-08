<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db_config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

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

$title = "ประวัติการเข้าสู่ระบบ";

// ตัวกรอง
$search_name   = trim($_GET['search_name'] ?? '');
$search_status = trim($_GET['search_status'] ?? '');
$current_month_start = date('Y-m-01');
$current_month_end   = date('Y-m-t');
$date_from = trim($_GET['date_from'] ?? $current_month_start);
$date_to   = trim($_GET['date_to'] ?? $current_month_end);
$export    = isset($_GET['export']) && $_GET['export'] === '1';
$per_page  = 15;
$page      = max(1, (int)($_GET['page'] ?? 1));

try {
    $baseSql = "
        FROM log_user_login l
        LEFT JOIN users u ON u.user_id = l.user_id
        LEFT JOIN user_profile p ON p.user_id = l.user_id
        LEFT JOIN user_rank r ON r.rank_id = p.rank_id
        WHERE 1=1
    ";
    $params = [];

    if ($search_name !== '') {
        $baseSql .= " AND (
            TRIM(CONCAT(IFNULL(r.rank_name, ''), ' ', IFNULL(p.first_name, ''), ' ', IFNULL(p.last_name, ''))) LIKE :sn
            OR u.email LIKE :sn
            OR l.email_user LIKE :sn
        )";
        $params[':sn'] = '%' . $search_name . '%';
    }

    if ($search_status !== '') {
        $baseSql .= " AND l.type_login = :status ";
        $params[':status'] = $search_status;
    }

    if ($date_from !== '') {
        $baseSql .= " AND DATE(l.date_login) >= :df ";
        $params[':df'] = $date_from;
    }
    if ($date_to !== '') {
        $baseSql .= " AND DATE(l.date_login) <= :dt ";
        $params[':dt'] = $date_to;
    }

    // Count
    $stmtCount = $pdo->prepare("SELECT COUNT(*) " . $baseSql);
    $stmtCount->execute($params);
    $countData   = (int)$stmtCount->fetchColumn();
    $total_pages = max(1, (int)ceil($countData / $per_page));
    if ($page > $total_pages) $page = $total_pages;
    $offset = ($page - 1) * $per_page;

    // สถิติ
    $stmtSuccess = $pdo->prepare("SELECT COUNT(*) " . $baseSql . " AND l.type_login = 0");
    $stmtSuccess->execute($params);
    $totalSuccess = (int)$stmtSuccess->fetchColumn();

    $stmtFail = $pdo->prepare("SELECT COUNT(*) " . $baseSql . " AND l.type_login = 1");
    $stmtFail->execute($params);
    $totalFail = (int)$stmtFail->fetchColumn();

    // Data
    $selectSql = "
        SELECT
            l.id,
            l.user_id,
            l.email_user,
            l.type_login,
            l.date_login,
            l.Ip_Address,
            l.location_predict,
            u.email AS user_email,
            TRIM(CONCAT(IFNULL(r.rank_name, ''), ' ', IFNULL(p.first_name, ''), ' ', IFNULL(p.last_name, ''))) AS full_name
    " . $baseSql . " ORDER BY l.date_login DESC ";

    if (!$export) {
        $selectSql .= " LIMIT :lim OFFSET :ofs";
    }

    $stmt = $pdo->prepare($selectSql);
    if (!$export) {
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':ofs', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt->execute($params);
    }
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logs = [];
    $countData = 0;
    $totalSuccess = 0;
    $totalFail = 0;
    $total_pages = 1;
    $page = 1;
    $error = $e->getMessage();
}

// Export Excel
if ($export) {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(14);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ประวัติการเข้าสู่ระบบ');

    $sheet->mergeCells('A1:G1');
    $sheet->setCellValue('A1', 'ประวัติการเข้าสู่ระบบ');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(35);

    $headers = ['ลำดับ', 'ชื่อผู้ใช้งาน', 'อีเมล', 'สถานะ', 'IP Address', 'พื้นที่', 'วันเวลา'];
    $sheet->fromArray($headers, null, 'A2');
    $sheet->getStyle('A2:G2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '60A5FA']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ]);

    $r = 3;
    $idx = 1;
    foreach ($logs as $row) {
        $name = trim($row['full_name']) !== '' ? trim($row['full_name']) : '-';
        $email = $row['email_user'] ?? $row['user_email'] ?? '-';
        $status = (int)$row['type_login'] === 0 ? 'สำเร็จ' : 'ไม่สำเร็จ';
        $sheet->fromArray([
            $idx++, $name, $email, $status,
            $row['Ip_Address'] ?? '-',
            $row['location_predict'] ?? '-',
            date('d/m/Y H:i:s', strtotime($row['date_login'])),
        ], null, 'A' . $r);

        $bgColor = ($r % 2 === 0) ? 'E8F4FD' : 'F8FBFF';
        $sheet->getStyle('A'.$r.':G'.$r)->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D4E4FF']]],
        ]);
        $r++;
    }

    foreach (range('A', 'G') as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }
    $sheet->freezePane('A3');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="login_history_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
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
    .badge-success-login { font-size: 12px; color: #212529; }
    .badge-fail-login    { font-size: 12px; color: #dc2626; }
    .pagination .page-link { font-size: 13px; }
    .pagination-wrap { max-width: 100%; overflow-x: auto; }
    .pagination-wrap .pagination { flex-wrap: nowrap; white-space: nowrap; }
</style>

<div class="container-fluid">
    <!-- Filter -->
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
                            <div class="col-md-3 col-sm-12">
                                <label class="form-label mb-0 text-muted" style="font-size:11px;">ชื่อ / อีเมล</label>
                                <input type="text" class="form-control form-control-sm" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>" placeholder="ค้นหาชื่อหรืออีเมล">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label mb-0 text-muted" style="font-size:11px;">สถานะ</label>
                                <select class="form-select form-select-sm" name="search_status">
                                    <option value="">ทั้งหมด</option>
                                    <option value="0" <?php echo $search_status === '0' ? 'selected' : ''; ?>>สำเร็จ</option>
                                    <option value="1" <?php echo $search_status === '1' ? 'selected' : ''; ?>>ไม่สำเร็จ</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label mb-0 text-muted" style="font-size:11px;">วันที่เริ่ม</label>
                                <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label mb-0 text-muted" style="font-size:11px;">วันที่สิ้นสุด</label>
                                <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-12 d-flex justify-content-center align-items-center gap-2 mt-3">
                                <a href="login_history.php" class="btn btn-warning btn-sm text-dark fw-bold px-3">
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

    <!-- Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-end align-items-center mb-3">
                <div class="text-muted small">
                    จำนวนข้อมูลทั้งหมด : <span><?php echo number_format($countData); ?></span> รายการ
                </div>
            </div>

            <?php if (!empty($error ?? '')): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered table-hover table-custom align-middle mb-0 table-striped">
                    <thead style="font-size:14px;">
                        <tr>
                            <th style="width:4%;">ลำดับ</th>
                            <th style="width:16%;">ชื่อผู้ใช้งาน</th>
                            <th style="width:16%;">อีเมล</th>
                            <th style="width:8%;">สถานะ</th>
                            <th style="width:12%;">IP Address</th>
                            <th style="width:22%;">พื้นที่โดยประมาณ</th>
                            <th style="width:14%;">วันเวลา</th>
                        </tr>
                    </thead>
                    <tbody style="font-size:13px;">
                        <?php if (!empty($logs)): ?>
                            <?php $index = $offset + 1; ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $displayName = trim($log['full_name']) !== '' ? trim($log['full_name']) : '-';
                                $displayEmail = $log['email_user'] ?? $log['user_email'] ?? '-';
                                $isSuccess = (int)$log['type_login'] === 0;
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $index++; ?></td>
                                    <td><?php echo htmlspecialchars($displayName); ?></td>
                                    <td><?php echo htmlspecialchars($displayEmail); ?></td>
                                    <td class="text-center">
                                        <?php if ($isSuccess): ?>
                                            <span style="font-size:12px;"><i class="fas fa-check-circle text-success me-1"></i>สำเร็จ</span>
                                        <?php else: ?>
                                            <span style="font-size:12px; color:#dc2626;"><i class="fas fa-times-circle me-1"></i>ไม่สำเร็จ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <code style="font-size:12px;"><?php echo htmlspecialchars($log['Ip_Address'] ?? '-'); ?></code>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($log['location_predict'] ?? '-'); ?></small>
                                    </td>
                                    <td class="text-center"><?php echo date('d/m/Y H:i:s', strtotime($log['date_login'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 opacity-50"></i>
                                    <div>ไม่พบข้อมูลประวัติการเข้าสู่ระบบ</div>
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
                $build_page_url = function($tp) use ($query_params) {
                    return 'login_history.php?' . http_build_query(array_merge($query_params, ['page' => $tp]));
                };
                $max_vis = 7;
                $half = (int)floor($max_vis / 2);
                $start_page = max(1, $page - $half);
                $end_page   = min($total_pages, $start_page + $max_vis - 1);
                $start_page = max(1, $end_page - $max_vis + 1);
                ?>
                <div class="d-flex justify-content-center mt-3">
                    <nav class="pagination-wrap">
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars($build_page_url(max(1, $page - 1))); ?>">&laquo;</a>
                            </li>
                            <?php if ($start_page > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($build_page_url(1)); ?>">1</a></li>
                                <?php if ($start_page > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($pn = $start_page; $pn <= $end_page; $pn++): ?>
                                <li class="page-item <?php echo $pn === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars($build_page_url($pn)); ?>"><?php echo $pn; ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($build_page_url($total_pages)); ?>"><?php echo $total_pages; ?></a></li>
                            <?php endif; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars($build_page_url(min($total_pages, $page + 1))); ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
