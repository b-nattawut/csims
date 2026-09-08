<?php
session_start();
// ตรวจสอบว่า login หรือยัง
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db_config.php';

// กำหนด Title ของหน้า
$title = "แก้ไขข้อมูลส่วนตัว";

// ดึงข้อมูลโปรไฟล์ของผู้ใช้
$user_id = $_SESSION['user_id'];
$success = $error = $password_success = $password_error = '';

try {
    // ดึงข้อมูล user + profile + rank + position
    $stmt = $pdo->prepare("
        SELECT u.email, u.role, u.created_at, u.nvt_sub, u.spt_sub, u.ptjv_sub, u.department_id,
               up.first_name, up.last_name, up.rank_id, up.position_id, up.phone,
               ur.rank_name, upos.position_name, md.department_name
        FROM users u
        LEFT JOIN user_profile up ON u.user_id = up.user_id
        LEFT JOIN user_rank ur ON up.rank_id = ur.rank_id
        LEFT JOIN user_position upos ON up.position_id = upos.position_id
        LEFT JOIN master_departments md ON u.department_id = md.id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();

    // ดึงข้อมูลยศทั้งหมด
    $stmt = $pdo->query("SELECT * FROM user_rank ORDER BY rank_id ASC");
    $ranks = $stmt->fetchAll();

    // ดึงข้อมูลตำแหน่งทั้งหมด
    $stmt = $pdo->query("SELECT * FROM user_position ORDER BY position_id ASC");
    $positions = $stmt->fetchAll();

    // ดึงข้อมูลหน่วยงานทั้งหมด
    $stmt = $pdo->query("SELECT id, department_name FROM master_departments WHERE status_delete = 0 ORDER BY department_name ASC");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// อัปเดตข้อมูลโปรไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['change_password'])) {
    try {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $rank_id = $_POST['rank_id'];
        $position_id = $_POST['position_id'];
        $department_id = $_POST['department_id'] !== '' ? $_POST['department_id'] : null;
        $phone = $_POST['phone'];
        $nvt_sub = trim($_POST['nvt_sub'] ?? '');
        $spt_sub = trim($_POST['spt_sub'] ?? '');
        $ptjv_sub = trim($_POST['ptjv_sub'] ?? '');
        $nvt_sub_val = $nvt_sub !== '' ? (int)$nvt_sub : null;
        $spt_sub_val = $spt_sub !== '' ? (int)$spt_sub : null;
        $ptjv_sub_val = $ptjv_sub !== '' ? (int)$ptjv_sub : null;

        $pdo->beginTransaction();

        if ($profile['first_name'] !== null) {
            // อัปเดต profile
            $stmt = $pdo->prepare(
                "UPDATE user_profile SET first_name = ?, last_name = ?, rank_id = ?, position_id = ?, phone = ? WHERE user_id = ?"
            );
            $stmt->execute([$first_name, $last_name, $rank_id, $position_id, $phone, $user_id]);
        } else {
            // เพิ่มใหม่
            $stmt = $pdo->prepare(
                "INSERT INTO user_profile (user_id, first_name, last_name, rank_id, position_id, phone) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$user_id, $first_name, $last_name, $rank_id, $position_id, $phone]);
        }

        // อัปเดต agency fields ใน users table
        $stmt = $pdo->prepare("UPDATE users SET department_id = ?, nvt_sub = ?, spt_sub = ?, ptjv_sub = ? WHERE user_id = ?");
        $stmt->execute([$department_id, $nvt_sub_val, $spt_sub_val, $ptjv_sub_val, $user_id]);

        $pdo->commit();
        $success = "บันทึกข้อมูลโปรไฟล์เรียบร้อย";

        // รีเฟรชโปรไฟล์
        $stmt = $pdo->prepare("
            SELECT u.email, u.role, u.created_at, u.nvt_sub, u.spt_sub, u.ptjv_sub, u.department_id,
                   up.first_name, up.last_name, up.rank_id, up.position_id, up.phone,
                   ur.rank_name, upos.position_name, md.department_name
            FROM users u
            LEFT JOIN user_profile up ON u.user_id = up.user_id
            LEFT JOIN user_rank ur ON up.rank_id = ur.rank_id
            LEFT JOIN user_position upos ON up.position_id = upos.position_id
            LEFT JOIN master_departments md ON u.department_id = md.id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch();

        // ====================================================================
        // อัปเดตค่าใน Session ปัจจุบันให้เป็นค่าล่าสุดตาม DB ทันที
        // ====================================================================
        $_SESSION['first_name']    = $profile['first_name'] ?? '';
        $_SESSION['last_name']     = $profile['last_name'] ?? '';
        $_SESSION['rank_name']     = $profile['rank_name'] ?? '';     // ยศใหม่
        $_SESSION['position_name'] = $profile['position_name'] ?? ''; // ตำแหน่งใหม่
        // ====================================================================

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
    }
}

// เปลี่ยนรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        // ดึงรหัสผ่านปัจจุบัน
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($old_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$hashed_password, $user_id]);

                    $password_success = "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว";
                } else {
                    $password_error = "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร";
                }
            } else {
                $password_error = "รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน";
            }
        } else {
            $password_error = "รหัสผ่านเดิมไม่ถูกต้อง";
        }
    } catch (PDOException $e) {
        $password_error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

ob_start();
?>

<style>
    .p-card { border:none; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.07); overflow:hidden; background:#fff; margin-bottom:1.5rem; }
    .p-card-head { padding:1rem 1.5rem; font-size:1.1rem; font-weight:700; display:flex; align-items:center; gap:.6rem; color:#fff; }
    .p-card-head.blue { background:linear-gradient(135deg,#0d6efd,#3c8ce7); }
    .p-card-head.gray { background:linear-gradient(135deg,#495057,#6c757d); }
    .p-card-body { padding:1.5rem; }

    .info-avatar { width:90px; height:90px; border-radius:50%; background:#e9ecef; display:flex; align-items:center; justify-content:center; font-size:2.5rem; color:#6c757d; border:3px solid #fff; box-shadow:0 3px 10px rgba(0,0,0,.1); margin:0 auto; }
    .info-name { font-size:1.2rem; font-weight:700; color:#212529; margin-top:.75rem; }
    .info-role { display:inline-block; padding:.2rem .7rem; border-radius:20px; font-size:.75rem; font-weight:600; }
    .info-role.admin { background:#fff3cd; color:#856404; }
    .info-role.user { background:#d1ecf1; color:#0c5460; }

    .info-list { list-style:none; padding:0; margin:1.25rem 0 0; }
    .info-list li { display:flex; align-items:center; gap:.6rem; padding:.55rem 0; border-bottom:1px solid #f0f0f0; font-size:.9rem; color:#495057; }
    .info-list li:last-child { border-bottom:none; }
    .info-list li i { width:20px; text-align:center; color:#0d6efd; font-size:.85rem; }
    .info-list li .lbl { color:#868e96; min-width:80px; }

    .btn-save { background:linear-gradient(135deg,#0d6efd,#3c8ce7); border:none; color:#fff; padding:.55rem 1.5rem; border-radius:8px; font-weight:600; transition:all .2s; }
    .btn-save:hover { box-shadow:0 4px 12px rgba(13,110,253,.3); color:#fff; }
    .btn-pass { background:linear-gradient(135deg,#ffc107,#ffdb58); border:none; color:#212529; padding:.55rem 1.5rem; border-radius:8px; font-weight:600; transition:all .2s; }
    .btn-pass:hover { box-shadow:0 4px 12px rgba(255,193,7,.3); }
</style>

<?php
$fn = htmlspecialchars($profile['first_name'] ?? '');
$ln = htmlspecialchars($profile['last_name'] ?? '');
$em = htmlspecialchars($profile['email'] ?? '');
$ph = htmlspecialchars($profile['phone'] ?? '');
$rn = htmlspecialchars($profile['rank_name'] ?? '-');
$pn = htmlspecialchars($profile['position_name'] ?? '-');
$dn = htmlspecialchars($profile['department_name'] ?? '-');
$rl = $profile['role'] ?? 'user';
$ca = isset($profile['created_at']) ? date('d/m/Y', strtotime($profile['created_at'])) : '-';
$fullName = $fn ? ($rn !== '-' ? $rn.' ' : '').$fn.' '.$ln : 'ยังไม่ได้ระบุชื่อ';
$nvt = $profile['nvt_sub'] ?? '';
$spt = $profile['spt_sub'] ?? '';
$ptjv = $profile['ptjv_sub'] ?? '';
$ptjvLabels = ['94'=>'ปัตตานี','95'=>'ยะลา','96'=>'นราธิวาส'];
$nvtDisplay = $nvt !== '' && $nvt !== null ? $nvt : '-';
$sptDisplay = $spt !== '' && $spt !== null ? $spt : '-';
$ptjvDisplay = isset($ptjvLabels[$ptjv]) ? $ptjvLabels[$ptjv] : ($ptjv !== '' && $ptjv !== null ? $ptjv : '-');
?>

<!-- Alerts -->
<div class="row justify-content-center">
    <div class="col-lg-11">
        <?php foreach(['success'=>$success,'error'=>$error,'password_success'=>$password_success,'password_error'=>$password_error] as $k=>$v): ?>
            <?php if($v): ?>
                <div class="alert alert-<?php echo strpos($k,'error')!==false?'danger':'success'; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo strpos($k,'error')!==false?'exclamation-triangle':'check-circle'; ?> me-2"></i><?php echo $v; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<div class="row justify-content-center">
    <!-- ===== ฝั่งซ้าย: ข้อมูลสรุป ===== -->
    <div class="col-lg-4 col-md-5 mb-3">
        <div class="p-card">
            <div class="p-card-head blue"><i class="fas fa-user-circle"></i> ข้อมูลของฉัน</div>
            <div class="p-card-body text-center">
                <div class="info-avatar"><i class="fas fa-user"></i></div>
                <div class="info-name"><?php echo $fullName; ?></div>
                <span class="info-role <?php echo $rl==='admin'?'admin':'user'; ?>"><?php echo $rl==='admin'?'ผู้ดูแลระบบ':'ผู้ใช้งาน'; ?></span>
                <ul class="info-list text-start">
                    <li><i class="fas fa-envelope"></i><span class="lbl">อีเมล</span><span><?php echo $em ?: '-'; ?></span></li>
                    <li><i class="fas fa-phone"></i><span class="lbl">โทรศัพท์</span><span><?php echo $ph ?: '-'; ?></span></li>
                    <li><i class="fas fa-medal"></i><span class="lbl">ยศ</span><span><?php echo $rn; ?></span></li>
                    <li><i class="fas fa-briefcase"></i><span class="lbl">ตำแหน่ง</span><span><?php echo $pn; ?></span></li>
                    <li><i class="fas fa-sitemap"></i><span class="lbl">หน่วยงาน</span><span><?php echo $dn; ?></span></li>
                    <li><i class="fas fa-building"></i><span class="lbl">นวท.(สบ)</span><span><?php echo $nvtDisplay; ?></span></li>
                    <li><i class="fas fa-building"></i><span class="lbl">ศพฐ</span><span><?php echo $sptDisplay; ?></span></li>
                    <li><i class="fas fa-building"></i><span class="lbl">พฐจว</span><span><?php echo $ptjvDisplay; ?></span></li>
                    <li><i class="fas fa-calendar"></i><span class="lbl">สมัครเมื่อ</span><span><?php echo $ca; ?></span></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ===== ฝั่งขวา: ฟอร์มแก้ไข ===== -->
    <div class="col-lg-7 col-md-7">
        <!-- แก้ไขข้อมูลพื้นฐาน -->
        <div class="p-card">
            <div class="p-card-head blue"><i class="fas fa-edit"></i> แก้ไขข้อมูลพื้นฐาน</div>
            <div class="p-card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ชื่อ</label>
                            <input type="text" class="form-control" name="first_name" value="<?php echo $fn; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">นามสกุล</label>
                            <input type="text" class="form-control" name="last_name" value="<?php echo $ln; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ยศ</label>
                            <select class="form-select" name="rank_id" required>
                                <option value="">เลือกยศ</option>
                                <?php foreach($ranks as $r): ?>
                                <option value="<?php echo $r['rank_id']; ?>" <?php echo isset($profile['rank_id'])&&$profile['rank_id']==$r['rank_id']?'selected':''; ?>><?php echo htmlspecialchars($r['rank_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ตำแหน่ง</label>
                            <select class="form-select" name="position_id" required>
                                <option value="">เลือกตำแหน่ง</option>
                                <?php foreach($positions as $p): ?>
                                <option value="<?php echo $p['position_id']; ?>" <?php echo isset($profile['position_id'])&&$profile['position_id']==$p['position_id']?'selected':''; ?>><?php echo htmlspecialchars($p['position_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">หน่วยงาน</label>
                            <select class="form-select" name="department_id" required>
                                <option value="">เลือกหน่วยงาน</option>
                                <?php foreach($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo isset($profile['department_id'])&&$profile['department_id']==$d['id']?'selected':''; ?>><?php echo htmlspecialchars($d['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">เบอร์โทรศัพท์</label>
                            <input type="text" class="form-control" name="phone" value="<?php echo $ph; ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">อีเมล</label>
                            <input type="email" class="form-control bg-light" value="<?php echo $em; ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">นวท.(สบ)</label>
                            <select class="form-select" name="nvt_sub">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php for($i=1;$i<=4;$i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $nvt==$i?'selected':''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ศพฐ</label>
                            <select class="form-select" name="spt_sub">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php for($i=1;$i<=10;$i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $spt==$i?'selected':''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">พฐจว</label>
                            <select class="form-select" name="ptjv_sub">
                                <option value="">-- ไม่ระบุ --</option>
                                <option value="94" <?php echo $ptjv=='94'?'selected':''; ?>>ปัตตานี</option>
                                <option value="95" <?php echo $ptjv=='95'?'selected':''; ?>>ยะลา</option>
                                <option value="96" <?php echo $ptjv=='96'?'selected':''; ?>>นราธิวาส</option>
                            </select>
                        </div>
                        <div class="col-12 mt-3 text-end">
                            <button type="submit" class="btn btn-save"><i class="fas fa-save me-1"></i> บันทึกข้อมูล</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- เปลี่ยนรหัสผ่าน -->
        <div class="p-card">
            <div class="p-card-head gray"><i class="fas fa-lock"></i> เปลี่ยนรหัสผ่าน</div>
            <div class="p-card-body">
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">รหัสผ่านเดิม</label>
                            <input type="password" class="form-control" name="old_password" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">รหัสผ่านใหม่</label>
                            <input type="password" class="form-control" name="new_password" required>
                            <div class="form-text small">อย่างน้อย 6 ตัวอักษร</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ยืนยันรหัสผ่าน</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <div class="col-12 mt-2 text-end">
                            <button type="submit" class="btn btn-pass"><i class="fas fa-key me-1"></i> เปลี่ยนรหัสผ่าน</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>