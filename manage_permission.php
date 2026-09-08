<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.html');
    exit;
}

require_once 'db_config.php';

$title = "กำหนดสิทธิ์การเข้าถึง";
$success = '';
$errors = [];

// ============ Handle POST: บันทึกสิทธิ์ ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    $role_name = $_POST['role_name'] ?? '';
    $permission_ids = $_POST['permissions'] ?? [];

    if (!in_array($role_name, ['admin', 'supervisor', 'user'])) {
        $errors[] = "Role ไม่ถูกต้อง";
    } else {
        try {
            // ลบสิทธิ์เดิมทั้งหมดของ role นี้
            $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_name = ?");
            $stmt->execute([$role_name]);

            // เพิ่มสิทธิ์ใหม่
            if (!empty($permission_ids)) {
                $insertStmt = $pdo->prepare("REPLACE INTO role_permissions (role_name, permission_id) VALUES (?, ?)");
                foreach ($permission_ids as $pid) {
                    $insertStmt->execute([$role_name, (int)$pid]);
                }
            }

            $success = "บันทึกสิทธิ์สำหรับ " . ucfirst($role_name) . " เรียบร้อยแล้ว";
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
        }
    }
}

// ============ ดึงข้อมูล ============
try {
    // ดึง permissions ทั้งหมด จัดกลุ่มตาม module
    $allPermissions = $pdo->query("SELECT * FROM permissions ORDER BY module, id")->fetchAll(PDO::FETCH_ASSOC);

    // จัดกลุ่มตาม module (ยกเว้น module ที่ไม่ต้องแสดงใน UI)
    $hiddenModules = ['dashboard'];
    // module ที่แสดงเฉพาะ view (ไม่มี CRUD)
    $viewOnlyModules = ['checklist', 'incident_report'];
    $permissionsByModule = [];
    foreach ($allPermissions as $perm) {
        if (in_array($perm['module'], $hiddenModules)) continue;
        // กรอง module ที่มีแค่ view
        if (in_array($perm['module'], $viewOnlyModules) && !str_ends_with($perm['name'], '.view')) continue;
        $permissionsByModule[$perm['module']][] = $perm;
    }

    // ดึงสิทธิ์ที่แต่ละ role มี
    $rolePermissions = [];
    $roles = ['admin', 'supervisor', 'user'];
    foreach ($roles as $role) {
        $stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_name = ?");
        $stmt->execute([$role]);
        $rolePermissions[$role] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // นับจำนวน user ในแต่ละ role
    $roleCounts = [];
    foreach ($roles as $role) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ? AND is_active = 1");
        $stmt->execute([$role]);
        $roleCounts[$role] = (int)$stmt->fetchColumn();
    }
} catch (PDOException $e) {
    $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    $permissionsByModule = [];
    $rolePermissions = [];
    $roleCounts = [];
}

// จัดกลุ่ม module ตามโครงสร้างเมนู sidebar
$moduleGroups = [
    [
        'group' => 'การจัดการคดี',
        'icon'  => 'fas fa-briefcase',
        'color' => '#0dcaf0',
        'modules' => [
            'incident'        => ['label' => 'รับแจ้งเหตุ',              'icon' => 'fas fa-file-alt'],
            'checklist'       => ['label' => 'แบบตรวจเก็บ',              'icon' => 'fas fa-clipboard-check'],
            'evidence'        => ['label' => 'วัตถุพยาน',                'icon' => 'fas fa-box-open'],
            'incident_report' => ['label' => 'ร่างรายงาน',               'icon' => 'fas fa-file-signature'],
            'report_review'   => ['label' => 'การตรวจร่างรายงาน',         'icon' => 'fas fa-user-check'],
            'report_extension'=> ['label' => 'ขอขยายเวลาออกรายงาน',      'icon' => 'fas fa-clock'],
            'field_visit_log' => ['label' => 'บันทึกรายงานภาคสนาม',      'icon' => 'fas fa-map-marked-alt'],
            'report'          => ['label' => 'รายงาน',                   'icon' => 'fas fa-chart-bar'],
        ]
    ],
    [
        'group' => 'ห้องปฏิบัติการ',
        'icon'  => 'fas fa-clipboard-check',
        'color' => '#198754',
        'modules' => [
            'equipment_usage' => ['label' => 'ใช้งานเครื่องมือทั่วไป',   'icon' => 'fas fa-wrench'],
            'computer_usage'  => ['label' => 'ใช้งานคอมพิวเตอร์',        'icon' => 'fas fa-laptop'],
            'maintenance'     => ['label' => 'ซ่อมบำรุง/สอบเทียบ',       'icon' => 'fas fa-cogs'],
            'temperature'     => ['label' => 'ควบคุมอุณหภูมิ',           'icon' => 'fas fa-thermometer-half'],
            'chemical'        => ['label' => 'สารเคมี',                  'icon' => 'fas fa-flask'],
            'readiness'       => ['label' => 'ความพร้อมเจ้าหน้าที่',     'icon' => 'fas fa-users'],
            'staff_assessment'=> ['label' => 'ประเมินความสามารถเจ้าหน้าที่','icon' => 'fas fa-user-graduate'],
        ]
    ],
    [
        'group' => 'Master Data',
        'icon'  => 'fas fa-database',
        'color' => '#343a40',
        'modules' => [
            'master_data'     => ['label' => 'Master Data',              'icon' => 'fas fa-database'],
        ]
    ],
    [
        'group' => 'อื่นๆ',
        'icon'  => 'fas fa-ellipsis-h',
        'color' => '#FFC107',
        'modules' => [
            'gps'             => ['label' => 'GPS Tracker',              'icon' => 'fas fa-van-shuttle'],
        ]
    ],
    [
        'group' => 'ระบบ',
        'icon'  => 'fas fa-cog',
        'color' => '#6c757d',
        'modules' => [
            'user'            => ['label' => 'จัดการผู้ใช้',             'icon' => 'fas fa-user-cog'],
            'permission'      => ['label' => 'กำหนดสิทธิ์',             'icon' => 'fas fa-shield-alt'],
        ]
    ],
];

// สร้าง flat moduleLabels จาก moduleGroups (ใช้ fallback)
$moduleLabels = [];
foreach ($moduleGroups as $g) {
    foreach ($g['modules'] as $k => $v) {
        $moduleLabels[$k] = $v + ['color' => $g['color']];
    }
}

// Action labels (ตรงกับปุ่มจริงบนหน้าเว็บ)
$actionLabels = [
    'view'    => ['label' => 'ดูข้อมูล',       'badge' => 'bg-info'],
    'create'  => ['label' => 'เพิ่มข้อมูล',    'badge' => 'bg-success'],
    'edit'    => ['label' => 'จัดการข้อมูล',    'badge' => 'bg-warning text-dark'],
    'delete'  => ['label' => 'ลบข้อมูล',       'badge' => 'bg-danger'],
    'export'  => ['label' => 'Export Excel',   'badge' => 'bg-success'],
    'manage'  => ['label' => 'จัดการ',         'badge' => 'bg-dark'],
];

// Role display config
$roleConfig = [
    'admin'      => ['label' => 'Admin',      'color' => '#dc3545', 'icon' => 'fas fa-user-shield'],
    'supervisor' => ['label' => 'Supervisor',  'color' => '#fd7e14', 'icon' => 'fas fa-user-tie'],
    'user'       => ['label' => 'User',        'color' => '#0d6efd', 'icon' => 'fas fa-user'],
];

// คำอธิบาย Role
$roleDescriptions = [
    'admin'      => 'ผู้ดูแลระบบ สิทธิ์สูงสุด จัดการได้ทั้งหมด',
    'supervisor' => 'หัวหน้างาน ตรวจสอบ อนุมัติ และดูรายงาน',
    'user'       => 'เจ้าหน้าที่ ใช้งานทั่วไปตามสิทธิ์ที่กำหนด',
];

// สรุปสิทธิ์ตามกลุ่มเมนูของแต่ละ Role
$roleModuleGroupSummary = [];
foreach ($roles as $role) {
    $rolePerms = $rolePermissions[$role] ?? [];
    foreach ($moduleGroups as $group) {
        $groupTotal = 0;
        $groupEnabled = 0;
        foreach ($group['modules'] as $mod => $mInfo) {
            if (!isset($permissionsByModule[$mod])) continue;
            foreach ($permissionsByModule[$mod] as $perm) {
                $groupTotal++;
                if (in_array($perm['id'], $rolePerms)) {
                    $groupEnabled++;
                }
            }
        }
        if ($groupTotal > 0) {
            $roleModuleGroupSummary[$role][] = [
                'group'   => $group['group'],
                'icon'    => $group['icon'],
                'color'   => $group['color'],
                'enabled' => $groupEnabled,
                'total'   => $groupTotal,
            ];
        }
    }
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
    .btn-close-white {
        filter: invert(1) grayscale(100%) brightness(200%);
    }
    .module-section {
        border: 1px solid #e9ecef;
        border-radius: 10px;
        margin-bottom: 12px;
        overflow: hidden;
    }
    .module-header {
        padding: 10px 16px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .module-header .module-title {
        font-weight: 700;
        font-size: 14px;
    }
    .module-body {
        padding: 12px 16px;
    }
    .perm-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #f1f3f5;
    }
    .perm-item:last-child { border-bottom: none; }
    .perm-item label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-size: 13px;
        margin: 0;
    }
    .perm-item .perm-desc {
        color: #6c757d;
        font-size: 12px;
    }
    .form-check-input:checked {
        background-color: #3468eb;
        border-color: #3468eb;
    }
    .module-check-all {
        font-size: 11px;
        cursor: pointer;
        color: #3468eb;
        font-weight: 600;
    }
    .module-check-all:hover { text-decoration: underline; }
    .modal-lg-custom { max-width: 650px; }

    /* Permission Group Mini Bars */
    .perm-group-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 0;
    }
    .perm-group-item + .perm-group-item {
        border-top: 1px solid #f1f3f5;
    }
    .perm-group-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .perm-group-name {
        font-size: 12px;
        font-weight: 600;
        color: #495057;
        min-width: 110px;
        white-space: nowrap;
    }
    .perm-mini-bar {
        flex: 1;
        height: 7px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        min-width: 60px;
        max-width: 160px;
    }
    .perm-mini-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.4s ease;
    }
    .perm-group-count {
        font-size: 11px;
        font-weight: 700;
        min-width: 35px;
        text-align: right;
    }
    .perm-group-check {
        font-size: 10px;
        width: 16px;
        text-align: center;
    }

    /* Role card row */
    .role-avatar {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 18px;
    }

    /* Overall progress circle */
    .perm-overview {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .perm-circle-wrap {
        width: 56px;
        height: 56px;
        position: relative;
    }
    .perm-circle-wrap svg {
        transform: rotate(-90deg);
    }
    .perm-circle-text {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 13px;
        line-height: 1.1;
    }
    .perm-circle-sub {
        font-size: 9px;
        font-weight: 600;
        color: #adb5bd;
    }

    .group-divider {
        padding: 8px 16px;
        background: linear-gradient(135deg, #f0f2f5, #e8ebef);
        font-weight: 800;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .5px;
        display: flex;
        align-items: center;
        gap: 8px;
        border-bottom: 2px solid #dee2e6;
    }
</style>

<div class="container-fluid">
    <!-- Toast -->
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

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-shield-alt me-2 text-primary"></i>รายการ Role ในระบบ
                </h6>
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2" style="font-size:12px;">
                    ทั้งหมด <?php echo count($roles); ?> Role
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-custom align-middle mb-0">
                    <thead style="font-size: 13px;">
                        <tr>
                            <th style="width:5%;">ลำดับที่</th>
                            <th style="width:16%;">Role</th>
                            <th style="width:6%;">ผู้ใช้</th>
                            <th style="width:43%;">สิทธิ์ตามกลุ่มเมนู</th>
                            <th style="width:10%;">ภาพรวม</th>
                            <th style="width:10%;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody style="font-size:13px;">
                        <?php $row_index = 1; foreach ($roles as $role):
                            $cfg = $roleConfig[$role];
                            $permCount = count($rolePermissions[$role] ?? []);
                            $totalPerms = count($allPermissions);
                            $userCount = $roleCounts[$role] ?? 0;
                            $desc = $roleDescriptions[$role] ?? '';
                            $pct = $totalPerms > 0 ? round($permCount / $totalPerms * 100) : 0;
                            $groupSummary = $roleModuleGroupSummary[$role] ?? [];
                            $circumference = 2 * 3.14159 * 22;
                            $dashOffset = $circumference - ($pct / 100) * $circumference;
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $row_index++; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="role-avatar" 
                                         style="background:<?php echo $cfg['color']; ?>12; color:<?php echo $cfg['color']; ?>; border:2px solid <?php echo $cfg['color']; ?>25;">
                                        <i class="<?php echo $cfg['icon']; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold" style="font-size:13px;"><?php echo $cfg['label']; ?></div>
                                        <div class="text-muted" style="font-size:10px; line-height:1.3;"><?php echo $desc; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="fw-bold" style="font-size:15px;"><?php echo $userCount; ?></div>
                                <div class="text-muted" style="font-size:10px;">คน</div>
                            </td>
                            <td class="ps-4">
                                <?php foreach ($groupSummary as $gs):
                                    $gPct = $gs['total'] > 0 ? round($gs['enabled'] / $gs['total'] * 100) : 0;
                                    $isFull = ($gs['enabled'] === $gs['total']);
                                    $isEmpty = ($gs['enabled'] === 0);
                                    $barColor = $isEmpty ? '#e9ecef' : $gs['color'];
                                ?>
                                <div class="perm-group-item">
                                    <span class="perm-group-dot" style="background:<?php echo $gs['color']; ?>;<?php echo $isEmpty ? 'opacity:.3;' : ''; ?>"></span>
                                    <span class="perm-group-name" style="<?php echo $isEmpty ? 'color:#c0c5cc;' : ''; ?>"><?php echo $gs['group']; ?></span>
                                    <div class="perm-mini-bar">
                                        <div class="perm-mini-bar-fill" style="width:<?php echo $gPct; ?>%; background:<?php echo $barColor; ?>;"></div>
                                    </div>
                                    <span class="perm-group-count" style="color:<?php echo $isEmpty ? '#c0c5cc' : '#495057'; ?>;"><?php echo $gs['enabled']; ?>/<?php echo $gs['total']; ?></span>
                                    <span class="perm-group-check">
                                        <?php if ($isFull): ?>
                                            <i class="fas fa-check-circle" style="color:<?php echo $gs['color']; ?>;"></i>
                                        <?php elseif (!$isEmpty): ?>
                                            <i class="fas fa-adjust text-secondary" style="font-size:10px;"></i>
                                        <?php else: ?>
                                            <i class="far fa-circle text-muted opacity-25" style="font-size:10px;"></i>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-center">
                                <div class="perm-overview">
                                    <div class="perm-circle-wrap">
                                        <svg width="56" height="56" viewBox="0 0 56 56">
                                            <circle cx="28" cy="28" r="22" fill="none" stroke="#e9ecef" stroke-width="5"/>
                                            <circle cx="28" cy="28" r="22" fill="none" 
                                                    stroke="<?php echo $cfg['color']; ?>" stroke-width="5"
                                                    stroke-dasharray="<?php echo $circumference; ?>" 
                                                    stroke-dashoffset="<?php echo $dashOffset; ?>"
                                                    stroke-linecap="round"/>
                                        </svg>
                                        <div class="perm-circle-text">
                                            <span style="color:<?php echo $cfg['color']; ?>;"><?php echo $pct; ?>%</span>
                                        </div>
                                    </div>
                                    <div class="perm-circle-sub mt-1"><?php echo $permCount; ?>/<?php echo $totalPerms; ?></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-primary rounded-pill px-3" style="font-size:12px;"
                                        data-bs-toggle="modal" data-bs-target="#permModal"
                                        data-role="<?php echo $role; ?>">
                                    <i class="fas fa-shield-alt me-1"></i>กำหนดสิทธิ์
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Permission Modal -->
<div class="modal fade" id="permModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg-custom">
        <div class="modal-content">
            <div class="modal-header text-white" id="permModalHeader" style="background:#3468eb;">
                <h5 class="modal-title" style="font-size:15px;">
                    <i class="fas fa-shield-alt me-2"></i>
                    <span id="permModalTitle">จัดการสิทธิ์</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="permForm">
                <input type="hidden" name="role_name" id="permRoleName">
                <input type="hidden" name="save_permissions" value="1">
                <div class="modal-body" style="max-height:60vh; overflow-y:auto;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted" style="font-size:12px;">เลือกสิทธิ์ที่ต้องการให้ Role นี้</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-primary btn-sm" style="font-size:11px;" id="btnCheckAll">
                                <i class="fas fa-check-double me-1"></i>เลือกทั้งหมด
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" style="font-size:11px;" id="btnUncheckAll">
                                <i class="fas fa-times me-1"></i>ล้างทั้งหมด
                            </button>
                        </div>
                    </div>

                    <?php foreach ($moduleGroups as $gIdx => $group):
                        // เช็คว่ากลุ่มนี้มี module ที่แสดงได้หรือไม่
                        $hasVisibleModules = false;
                        foreach ($group['modules'] as $mod => $mInfo) {
                            if (isset($permissionsByModule[$mod])) { $hasVisibleModules = true; break; }
                        }
                        if (!$hasVisibleModules) continue;
                    ?>
                    <div class="group-divider">
                        <i class="<?php echo $group['icon']; ?>" style="color:<?php echo $group['color']; ?>;"></i>
                        <span style="color:<?php echo $group['color']; ?>;"><?php echo $group['group']; ?></span>
                    </div>

                    <?php foreach ($group['modules'] as $module => $mInfo):
                        if (!isset($permissionsByModule[$module])) continue;
                        $perms = $permissionsByModule[$module];
                        $ml = $moduleLabels[$module] ?? ['label' => $module, 'icon' => 'fas fa-cog', 'color' => '#6c757d'];
                    ?>
                    <div class="module-section">
                        <div class="module-header">
                            <i class="<?php echo $ml['icon']; ?>" style="color:<?php echo $ml['color']; ?>;"></i>
                            <span class="module-title"><?php echo $ml['label']; ?></span>
                            <span class="ms-auto module-check-all" data-module="<?php echo $module; ?>">เลือกทั้งหมด</span>
                        </div>
                        <div class="module-body">
                            <?php
                            // Module ที่แสดงแค่ view + create (เปลี่ยนชื่อเป็น "การจัดการข้อมูล")
                            $simpleModules = ['equipment_usage', 'computer_usage', 'maintenance', 'temperature', 'chemical', 'readiness', 'staff_assessment'];
                            foreach ($perms as $perm):
                                $action = explode('.', $perm['name'])[1] ?? '';

                                // ซ่อน edit/delete สำหรับ module ที่ใช้แค่ view + manage
                                if (in_array($module, $simpleModules) && in_array($action, ['edit', 'delete'])) continue;

                                $al = $actionLabels[$action] ?? ['label' => $action, 'badge' => 'bg-secondary'];

                                // เปลี่ยนชื่อ create → การจัดการข้อมูล สำหรับ module เหล่านี้
                                if (in_array($module, $simpleModules) && $action === 'create') {
                                    $al['label'] = 'การจัดการข้อมูล';
                                }
                            ?>
                            <div class="perm-item">
                                <label>
                                    <input type="checkbox" class="form-check-input perm-checkbox"
                                           name="permissions[]"
                                           value="<?php echo $perm['id']; ?>"
                                           data-module="<?php echo $module; ?>">
                                    <span style="font-size:12px; font-weight:600; color:#333;"><?php echo $al['label']; ?></span>
                                </label>
                                <span class="perm-desc"><?php echo htmlspecialchars($perm['name']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <span class="me-auto text-muted" style="font-size:12px;">
                        เลือกแล้ว: <strong id="selectedCount">0</strong>/<?php echo count($allPermissions); ?> สิทธิ์
                    </span>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i>บันทึกสิทธิ์
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
ob_start();
?>
<script>
$(document).ready(function() {
    var rolePermissions = <?php echo json_encode($rolePermissions); ?>;
    var roleConfig = <?php echo json_encode($roleConfig); ?>;

    // เปิด modal → set role + check permissions
    $('#permModal').on('show.bs.modal', function(event) {
        var role = $(event.relatedTarget).data('role');
        if (!role) return;

        $('#permRoleName').val(role);
        var cfg = roleConfig[role] || {};
        $('#permModalTitle').text('จัดการสิทธิ์: ' + (cfg.label || role));
        $('#permModalHeader').css('background', cfg.color || '#3468eb');

        // Reset all checkboxes
        $('.perm-checkbox').prop('checked', false);

        // Check permissions for this role
        var perms = rolePermissions[role] || [];
        perms.forEach(function(pid) {
            $('.perm-checkbox[value="' + pid + '"]').prop('checked', true);
        });

        updateSelectedCount();
    });

    // Count selected
    function updateSelectedCount() {
        $('#selectedCount').text($('.perm-checkbox:checked').length);
    }

    $(document).on('change', '.perm-checkbox', function() {
        updateSelectedCount();
    });

    // Check all
    $('#btnCheckAll').on('click', function() {
        $('.perm-checkbox').prop('checked', true);
        updateSelectedCount();
    });

    // Uncheck all
    $('#btnUncheckAll').on('click', function() {
        $('.perm-checkbox').prop('checked', false);
        updateSelectedCount();
    });

    // Module check all
    $('.module-check-all').on('click', function() {
        var mod = $(this).data('module');
        var checkboxes = $('.perm-checkbox[data-module="' + mod + '"]');
        var allChecked = checkboxes.filter(':checked').length === checkboxes.length;
        checkboxes.prop('checked', !allChecked);
        $(this).text(allChecked ? 'เลือกทั้งหมด' : 'ยกเลิกทั้งหมด');
        updateSelectedCount();
    });

    // Toast
    $('.toast.show').each(function() {
        new bootstrap.Toast(this, { delay: 3000 }).show();
    });
});
</script>
<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>
