<?php
session_start();
// ตรวจสอบว่าเป็น admin และล็อกอินแล้ว
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.html');
    exit;
}

require_once 'db_config.php';

$title = "แผงควบคุมการจัดการผู้ใช้";
$success = '';
$errors = [];
$SUPER_ADMIN_ID = 1;


// อัปเดต role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $role = $_POST['role'];

    if ($user_id === $SUPER_ADMIN_ID) {
        $errors[] = "ไม่สามารถแก้ไขบัญชี Super Admin ได้";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $stmt->execute([$role, $user_id]);
            $success = "อัปเดตบทบาทเรียบร้อย";
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการอัปเดตบทบาท: โปรดลองใหม่";
        }
    }
}

// อัปเดต is_active (รองรับทั้ง update_status ใหม่ และ toggle_active เดิม)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    (isset($_POST['update_status']) || isset($_POST['toggle_active']))
) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $is_active = ((string)($_POST['is_active'] ?? '1') === '1') ? 1 : 0;

    // ป้องกันการแก้ไข Super Admin และป้องกัน admin ปิดใช้งานตัวเอง
    if ($user_id === $SUPER_ADMIN_ID) {
        $errors[] = "ไม่สามารถแก้ไขบัญชี Super Admin ได้";
    } elseif ($user_id === (int)$_SESSION['user_id'] && $is_active === 0) {
        $errors[] = "คุณไม่สามารถปิดใช้งานบัญชีของตัวเองได้";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
            $stmt->execute([$is_active, $user_id]);
            $success = "อัปเดตสถานะการใช้งานเรียบร้อย";
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการอัปเดตสถานะ: โปรดลองใหม่";
        }
    }
}

// อัปเดต rank
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rank'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $rank_id = (int)($_POST['rank_id'] ?? 0);

    if ($user_id === $SUPER_ADMIN_ID) {
        $errors[] = "ไม่สามารถแก้ไขบัญชี Super Admin ได้";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE user_profile SET rank_id = ? WHERE user_id = ?");
            $stmt->execute([$rank_id, $user_id]);
            $success = "อัปเดตยศเรียบร้อย";
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการอัปเดตยศ: โปรดลองใหม่";
        }
    }
}

// อัปเดต position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_position'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $position_id = (int)($_POST['position_id'] ?? 0);

    if ($user_id === $SUPER_ADMIN_ID) {
        $errors[] = "ไม่สามารถแก้ไขบัญชี Super Admin ได้";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE user_profile SET position_id = ? WHERE user_id = ?");
            $stmt->execute([$position_id, $user_id]);
            $success = "อัปเดตตำแหน่งเรียบร้อย";
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการอัปเดตตำแหน่ง: โปรดลองใหม่";
        }
    }
}

// อัปเดต agency (หน่วยงาน)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_agency'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $department_id = (int)($_POST['agency_type'] ?? 0);

    if ($user_id === $SUPER_ADMIN_ID) {
        $errors[] = "ไม่สามารถแก้ไขบัญชี Super Admin ได้";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET department_id = ? WHERE user_id = ?");
            $stmt->execute([
                $department_id > 0 ? $department_id : null,
                $user_id
            ]);
            $success = "อัปเดตหน่วยงานเรียบร้อย";
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการอัปเดตหน่วยงาน: โปรดลองใหม่";
        }
    }
}

// เปลี่ยนรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // ตรวจสอบข้อมูล
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
    } elseif ($password !== $confirm_password) {
        $errors[] = "รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน";
    } elseif ($user_id === $SUPER_ADMIN_ID) {
        $errors[] = "ไม่สามารถแก้ไขบัญชี Super Admin ได้";
    } elseif ($user_id == $_SESSION['user_id']) {
        $errors[] = "คุณไม่สามารถเปลี่ยนรหัสผ่านของตัวเองในหน้านี้ได้ กรุณาใช้หน้าโปรไฟล์";
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $success = "เปลี่ยนรหัสผ่านเรียบร้อย";
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน: โปรดลองใหม่";
        }
    }
}


// ดึงข้อมูลผู้ใช้ทั้งหมด พร้อม JOIN ตารางโปรไฟล์และยศ
try {
    $sql = "
        SELECT 
            u.user_id, u.email, u.role, u.is_active, u.created_at,
            u.department_id, 
            td.department_name, 
            up.first_name, up.last_name, up.rank_id, up.position_id,
            ur.rank_name, upos.position_name
        FROM 
            users u
        LEFT JOIN 
            user_profile up ON u.user_id = up.user_id
        LEFT JOIN 
            user_rank ur ON up.rank_id = ur.rank_id
        LEFT JOIN 
            user_position upos ON up.position_id = upos.position_id
        LEFT JOIN 
            master_departments td ON u.department_id = td.id
        WHERE
            u.user_id <> ?
        ORDER BY 
            u.user_id
    ";

    // ดึงข้อมูลยศและตำแหน่งสำหรับ dropdown
    $allRanks = $pdo->query("SELECT rank_id, rank_name FROM user_rank ORDER BY rank_id")->fetchAll();
    $allPositions = $pdo->query("SELECT position_id, position_name FROM user_position ORDER BY position_id")->fetchAll();
    $allDepartments = $pdo->query("SELECT id, department_name FROM master_departments WHERE status_delete = 0 ORDER BY department_name ASC")->fetchAll();
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$SUPER_ADMIN_ID]);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: โปรดลองใหม่";
}

// เริ่มต้นเก็บเนื้อหา HTML ไว้ในตัวแปร $content
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

    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_length {
        display: none;
    }

    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        font-size: 13px;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #3468eb !important;
        color: #fff !important;
        border-color: #3468eb !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #dbe6ff !important;
        color: #1f2937 !important;
    }

    .table-self-row {
        background: rgba(52, 104, 235, 0.06) !important;
    }

    .table-inactive-row td {
        background: #fff5f5 !important;
        color: #6c757d;
    }

    .modal-header.bg-primary {
        background-color: #3468eb !important;
    }

    .modal-header.bg-info {
        background-color: #3ca7ff !important;
    }

    .btn-close-white {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    .pagination .page-link {
        font-size: 13px;
    }

    .btn-purple {
        background-color: #8b5cf6;
        border-color: #8b5cf6;
        color: #fff;
    }

    .btn-purple:hover {
        background-color: #7c3aed;
        border-color: #7c3aed;
        color: #fff;
    }

    .btn-purple:disabled {
        background-color: #c4b5fd;
        border-color: #c4b5fd;
        color: #fff;
    }

    .modal-header.bg-purple {
        background-color: #8b5cf6 !important;
    }

    .filter-field-compact {
        width: 220px;
    }

    @media (max-width: 768px) {
        .filter-field-compact {
            width: 100%;
        }
    }

    .text-ellipsis-cell {
        display: block;
        min-width: 170px;
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
        overflow-wrap: anywhere;
        line-height: 1.25;
    }

    .role-select,
    .status-select {
        min-width: 95px;
    }

    @media (max-width: 768px) {
        .text-ellipsis-cell {
            min-width: 140px;
        }

        .role-select,
        .status-select {
            min-width: 88px;
        }
    }
</style>
<div class="container-fluid">
    <!-- Toast แจ้งเตือนมุมล่างขวา -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080;">
        <?php if ($success): ?>
            <div class="toast align-items-center text-bg-success border-0 show" role="alert" id="toastSuccess">
                <div class="d-flex">
                    <div class="toast-body" style="font-size:13px;">
                        <i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="toast align-items-center text-bg-danger border-0 show" role="alert" id="toastError">
                <div class="d-flex">
                    <div class="toast-body" style="font-size:13px;">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <?php echo htmlspecialchars(implode(', ', $errors)); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card mb-3 shadow-sm" style="font-size:13px;">
        <div class="card-body p-2">
            <div class="d-flex flex-wrap justify-content-end align-items-center mb-2">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#userFilterSection" aria-expanded="true">
                    <i class="fas fa-filter me-1"></i> ตัวกรอง
                </button>
            </div>

            <div class="collapse show" id="userFilterSection">
                <div class="bg-light p-3 rounded-3 border mb-2 shadow-sm">
                    <form id="userFilterForm">
                        <div class="row g-2 align-items-end justify-content-center">
                            <div class="col-12 col-md-auto filter-field-compact">
                                <label class="form-label mb-0 text-muted" style="font-size: 11px;">อีเมล</label>
                                <input type="text" class="form-control form-control-sm" id="filter_email" placeholder="ค้นหาอีเมล">
                            </div>
                            <div class="col-12 col-md-auto filter-field-compact">
                                <label class="form-label mb-0 text-muted" style="font-size: 11px;">ชื่อ - นามสกุล</label>
                                <input type="text" class="form-control form-control-sm" id="filter_name" placeholder="ค้นหาชื่อ">
                            </div>
                            <div class="col-auto" style="min-width:140px;">
                                <label class="form-label mb-0 text-muted" style="font-size: 11px;">ยศ</label>
                                <select class="form-select form-select-sm" id="filter_rank">
                                    <option value="">กรุณาเลือก</option>
                                    <?php foreach ($allRanks as $rank): ?>
                                        <option value="<?php echo $rank['rank_id']; ?>"><?php echo htmlspecialchars($rank['rank_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto" style="min-width:160px;">
                                <label class="form-label mb-0 text-muted" style="font-size: 11px;">ตำแหน่ง</label>
                                <select class="form-select form-select-sm" id="filter_position">
                                    <option value="">กรุณาเลือก</option>
                                    <?php foreach ($allPositions as $pos): ?>
                                        <option value="<?php echo $pos['position_id']; ?>"><?php echo htmlspecialchars($pos['position_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-auto" style="min-width:160px;">
                                <label class="form-label mb-0 text-muted" style="font-size: 11px;">หน่วยงาน</label>
                                <select class="form-select form-select-sm agency-type-select" id="filter_department">
                                    <option value="">กรุณาเลือก</option>
                                    <?php foreach ($allDepartments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-auto" style="min-width:130px;">
                                <label class="form-label mb-0 text-muted" style="font-size: 11px;">บทบาท</label>
                                <select class="form-select form-select-sm" id="filter_role">
                                    <option value="">กรุณาเลือก</option>
                                    <option value="admin">Admin</option>
                                    <option value="supervisor">Supervisor</option>
                                    <option value="user">User</option>
                                </select>
                            </div>
                            <div class="col-auto" style="min-width:130px;">
                                <label class="form-label mb-0 text-muted" style="font-size: 11px;">สถานะ</label>
                                <select class="form-select form-select-sm" id="filter_status">
                                    <option value="">กรุณาเลือก</option>
                                    <option value="1">ใช้งาน</option>
                                    <option value="0">ไม่ใช้งาน</option>
                                </select>
                            </div>
                            <div class="col-auto d-flex gap-2">
                                <button type="button" class="btn btn-warning btn-sm text-dark fw-bold" id="btnResetUserFilter">
                                    <i class="fas fa-undo me-1"></i> ล้างค่า
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm fw-bold">
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
            <div class="d-flex justify-content-end align-items-center mb-3">
                <div class="text-muted small">
                    จำนวนข้อมูลทั้งหมด : <span id="count_display" class="fw-bold"><?php echo count($users); ?></span> รายการ
                </div>
            </div>
            <div class="table-responsive">
                <table id="userTable" class="table table-bordered table-hover table-custom align-middle mb-0">
                    <thead style="font-size: 14px;">
                        <tr>
                            <th style="width:4%;">ลำดับที่</th>
                            <th style="width:8%;">การจัดการ</th>
                            <th style="width:14%;">อีเมล</th>
                            <th style="width:14%;">ชื่อ - นามสกุล</th>
                            <th style="width:12%;">ยศ</th>
                            <th style="width:10%;">ตำแหน่ง</th>
                            <th style="width:10%;">หน่วยงาน</th>
                            <th style="width:8%;">บทบาท</th>
                            <th style="width:9%;">ปรับสถานะ</th>
                            <th style="width:10%;">วันที่สร้าง</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 13px;">
                        <?php $row_index = 1;
                        foreach ($users as $user):
                            $full_name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                            $is_self = ($user['user_id'] == $_SESSION['user_id']);
                            $row_class = $is_self ? 'table-self-row' : '';
                            if (!$user['is_active']) $row_class .= ' table-inactive-row';
                        ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td class="text-center"><?php echo $row_index++; ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-purple" style="font-size:12px;" data-bs-toggle="modal"
                                        data-bs-target="#changePasswordModal"
                                        data-user-id="<?php echo $user['user_id']; ?>">
                                        <i class="fas fa-key me-1"></i>รหัสผ่าน
                                    </button>
                                </td>
                                <td>
                                    <span class="text-ellipsis-cell" title="<?php echo htmlspecialchars($user['email']); ?>">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-ellipsis-cell" title="<?php echo $full_name; ?>">
                                        <?php echo $full_name; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <form method="POST" class="rank-form mb-0">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="update_rank" value="1">
                                        <select name="rank_id" class="form-select form-select-sm rank-select" style="font-size:12px;">
                                            <option value="">-- เลือกยศ --</option>
                                            <?php foreach ($allRanks as $rank): ?>
                                                <option value="<?php echo $rank['rank_id']; ?>" <?php echo ((int)($user['rank_id'] ?? 0) === (int)$rank['rank_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($rank['rank_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <form method="POST" class="position-form mb-0">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="update_position" value="1">
                                        <select name="position_id" class="form-select form-select-sm position-select" style="font-size:12px;">
                                            <option value="">-- เลือกตำแหน่ง --</option>
                                            <?php foreach ($allPositions as $pos): ?>
                                                <option value="<?php echo $pos['position_id']; ?>" <?php echo ((int)($user['position_id'] ?? 0) === (int)$pos['position_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($pos['position_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <form method="POST" class="agency-form mb-0">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="update_agency" value="1">
                                        <select name="agency_type"
                                            class="form-select form-select-sm agency-type-select"
                                            style="font-size:12px;">
                                            <option value="">-- เลือกหน่วยงาน --</option>
                                            <?php foreach ($allDepartments as $dept): ?>
                                                <option value="<?php echo $dept['id']; ?>" <?php echo ((int)($user['department_id'] ?? 0) === (int)$dept['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>

                                </td>
                                <td class="text-center">
                                    <form method="POST" class="role-form mb-0">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="update_role" value="1">
                                        <select name="role" class="form-select form-select-sm role-select" style="font-size:12px;">
                                            <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                            <option value="supervisor" <?php echo $user['role'] === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <form method="POST" class="status-form mb-0">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="is_active" class="form-select form-select-sm status-select" style="font-size:12px;" <?php echo ($is_self) ? 'disabled' : ''; ?>>
                                            <option value="1" <?php echo $user['is_active'] ? 'selected' : ''; ?>>ใช้งาน</option>
                                            <option value="0" <?php echo !$user['is_active'] ? 'selected' : ''; ?>>ไม่ใช้งาน</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-center" style="font-size:12px;"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center mt-3">
                <ul id="user-pagination" class="pagination mb-0"></ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-purple text-white">
                <h5 class="modal-title" id="changePasswordModalLabel"><i class="fas fa-key me-2"></i> เปลี่ยนรหัสผ่าน</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="modal_password_user_id">
                    <div class="alert alert-primary border-0 rounded-3 shadow-sm" role="alert">
                        <i class="fas fa-info-circle me-2"></i> **รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร**
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                        <input type="password" class="form-control" id="new_password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">ยืนยันรหัสผ่าน</label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" name="change_password" class="btn btn-purple"><i class="fas fa-sync-alt me-2"></i>เปลี่ยนรหัสผ่าน</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean(); // จบการเก็บเนื้อหา HTML
// 3. เริ่มต้นเก็บ JavaScript ไว้ในตัวแปร $extra_scripts
ob_start();
?>

<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap5.min.js"></script>
<script src="js/jquery.twbsPagination.min.js"></script>
<script>
    $(document).ready(function() {
        var userTable = $('#userTable').DataTable({
            "language": {
                "url": "js/th.json"
            },
            "pageLength": 10,
            "dom": 'rt',
            "order": [
                [0, "asc"]
            ]
        });

        function setupTwbsPagination() {
            var info = userTable.page.info();
            var totalPages = info.pages;
            var currentPage = info.page + 1;
            $('#user-pagination').twbsPagination('destroy');
            if (totalPages <= 1) return;
            setTimeout(function() {
                $('#user-pagination').twbsPagination({
                    totalPages: totalPages,
                    startPage: currentPage,
                    visiblePages: 5,
                    first: '<i class="fas fa-angle-double-left"></i>',
                    prev: '<i class="fas fa-angle-left"></i>',
                    next: '<i class="fas fa-angle-right"></i>',
                    last: '<i class="fas fa-angle-double-right"></i>',
                    initiateStartPageClick: false,
                    onPageClick: function(event, page) {
                        userTable.page(page - 1).draw('page');
                    }
                });
            }, 50);
        }

        // ฟิลเตอร์ email / name / rank / position / role / status
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'userTable') return true;

            var emailFilter = ($('#filter_email').val() || '').toLowerCase();
            var nameFilter = ($('#filter_name').val() || '').toLowerCase();
            var rankFilter = $('#filter_rank').val() || '';
            var positionFilter = $('#filter_position').val() || '';
            var deptFilter = $('#filter_department').val() || '';
            var roleFilter = ($('#filter_role').val() || '').toLowerCase();
            var statusFilter = $('#filter_status').val() || '';

            var emailText = (data[2] || '').toLowerCase();
            var nameText = (data[3] || '').toLowerCase();

            var rowNode = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
            var rankVal = rowNode ? ($(rowNode).find('select.rank-select').val() || '') : '';
            var posVal = rowNode ? ($(rowNode).find('select.position-select').val() || '') : '';
            var deptVal = rowNode ? ($(rowNode).find('select.agency-type-select').val() || '') : '';
            var roleVal = rowNode ? ($(rowNode).find('select.role-select').val() || '').toLowerCase() : '';
            var statusVal = rowNode ? ($(rowNode).find('select.status-select').val() || '') : '';

            return (!emailFilter || emailText.indexOf(emailFilter) !== -1) &&
                (!nameFilter || nameText.indexOf(nameFilter) !== -1) &&
                (!rankFilter || rankVal === rankFilter) &&
                (!positionFilter || posVal === positionFilter) &&
                (!deptFilter || deptVal === deptFilter) &&
                (!roleFilter || roleVal === roleFilter) &&
                (!statusFilter || statusVal === statusFilter);
        });

        $('#userFilterForm').on('submit', function(e) {
            e.preventDefault();
            userTable.page(0).draw();
        });

        $('#filter_role, #filter_status, #filter_rank, #filter_position, #filter_department').on('change', function() {
            userTable.page(0).draw();
        });

        $('#btnResetUserFilter').on('click', function() {
            $('#filter_email').val('');
            $('#filter_name').val('');
            $('#filter_rank').val('');
            $('#filter_position').val('');
            $('#filter_department').val('');
            $('#filter_role').val('');
            $('#filter_status').val('');
            userTable.page(0).draw();
        });

        userTable.on('draw', function() {
            var info = userTable.page.info();
            $('#count_display').text(info.recordsDisplay);
            setupTwbsPagination();
        });

        setupTwbsPagination();

        $('.status-select').on('change', function() {
            $(this).closest('form').submit();
        });

        $('.role-select').on('change', function() {
            $(this).closest('form').submit();
        });

        $('.rank-select').on('change', function() {
            $(this).closest('form').submit();
        });

        $('.position-select').on('change', function() {
            $(this).closest('form').submit();
        });

        $('.agency-form .agency-type-select').on('change', function() {
            $(this).closest('form').submit();
        });

        $('.toast.show').each(function() {
            new bootstrap.Toast(this, {
                delay: 3000
            }).show();
        });

        $('#changePasswordModal').on('show.bs.modal', function(event) {
            var modal = $(this);
            modal.find('#modal_password_user_id').val($(event.relatedTarget).data('user-id'));
            modal.find('#new_password, #confirm_new_password').val('');
        });

        $('#changePasswordModal form').on('submit', function(e) {
            var pw = document.getElementById('new_password').value;
            var cpw = document.getElementById('confirm_new_password').value;
            if (pw.length < 6) {
                alert('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
                e.preventDefault();
            } else if (pw !== cpw) {
                alert('รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน');
                e.preventDefault();
            }
        });
    });
</script>

<?php
$extra_scripts = ob_get_clean(); // จบการเก็บสคริปต์

// 4. เรียกใช้ layout.php
include 'layout.php';
?>