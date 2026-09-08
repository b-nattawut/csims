<?php
require_once 'db_config.php';

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $email = strtolower(trim($_POST['email'] ?? ''));

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name_en = trim($_POST['first_name_en'] ?? '');
    $last_name_en = trim($_POST['last_name_en'] ?? '');
    $rank_id = $_POST['rank_id'] ?? '';
    $position_id = $_POST['position_id'] ?? '';
    $department_id = $_POST['department_id'] ?? '';
    $phone_input = trim($_POST['phone'] ?? '');
    $phone_digits = preg_replace('/\D+/', '', $phone_input);
    $phone = '';
    if ($phone_input !== '') {
        if (strlen($phone_digits) === 10) {
            $phone = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6, 4);
        }
    }
    $nvt_sub  = trim($_POST['nvt_sub'] ?? '');
    $spt_sub  = trim($_POST['spt_sub'] ?? '');
    $ptjv_sub = trim($_POST['ptjv_sub'] ?? '');
    // ตรวจสอบค่า agency sub ให้เป็นตัวเลขที่ถูกต้อง
    if ($nvt_sub !== '' && (!ctype_digit($nvt_sub) || $nvt_sub < 1 || $nvt_sub > 4)) $nvt_sub = '';
    if ($spt_sub !== '' && (!ctype_digit($spt_sub) || $spt_sub < 1 || $spt_sub > 10)) $spt_sub = '';
    if ($ptjv_sub !== '' && !in_array($ptjv_sub, ['94','95','96'])) $ptjv_sub = '';

    // ตรวจสอบข้อมูล
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'กรุณากรอกอีเมลที่ถูกต้อง';
    } elseif (!str_ends_with($email, '@gmail.com')) {
        $errors[] = 'อีเมลต้องเป็น @gmail.com เท่านั้น';
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    }
    if (empty($first_name) || empty($last_name)) {
        $errors[] = 'กรุณากรอกชื่อและนามสกุล';
    }
    if (empty($first_name_en) || empty($last_name_en)) {
        $errors[] = 'กรุณากรอกชื่อและนามสกุล (ภาษาอังกฤษ)';
    }
    if ($phone_input !== '' && $phone === '') {
        $errors[] = 'เบอร์โทรศัพท์ต้องเป็นรูปแบบ 080-000-0000';
    }
    // การตรวจสอบ ยศ และ ตำแหน่ง (เนื่องจากเป็น Foreign Key ควรตรวจสอบค่า)
    if (empty($rank_id) || $rank_id === '') { 
        $errors[] = 'กรุณาเลือกยศ';
    }
    if (empty($department_id) || $department_id === '') {
        $errors[] = 'กรุณาเลือกหน่วยงาน';
    }
    if (empty($position_id) || $position_id === '') {
        $errors[] = 'กรุณาเลือกตำแหน่ง';
    }

    // ตรวจสอบอีเมลซ้ำ
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'อีเมลนี้ถูกใช้งานแล้ว';
        }
    } catch (PDOException $e) {
        $errors[] = 'เกิดข้อผิดพลาดในการตรวจสอบอีเมล: โปรดลองใหม่';
    }

    // หากไม่มีข้อผิดพลาด บันทึกข้อมูล
    if (empty($errors)) {
        try {
            // เริ่ม transaction
            $pdo->beginTransaction();

            // บันทึกข้อมูลลงตาราง users
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $role = 'user'; // ค่าเริ่มต้นสำหรับผู้ใช้ทั่วไป
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role, department_id, nvt_sub, spt_sub, ptjv_sub) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $nvt_sub_val  = $nvt_sub !== '' ? (int)$nvt_sub : null;
            $spt_sub_val  = $spt_sub !== '' ? (int)$spt_sub : null;
            $ptjv_sub_val = $ptjv_sub !== '' ? (int)$ptjv_sub : null;
            $department_id_val = $department_id !== '' ? (int)$department_id : null;
            $stmt->execute([$email, $hashed_password, $role, $department_id_val, $nvt_sub_val, $spt_sub_val, $ptjv_sub_val]);
            $user_id = $pdo->lastInsertId();

            // บันทึกข้อมูลลงตาราง user_profile
            $stmt = $pdo->prepare(
                "INSERT INTO user_profile (user_id, first_name, last_name, first_name_en, last_name_en, rank_id, position_id, phone) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$user_id, $first_name, $last_name, $first_name_en, $last_name_en, $rank_id, $position_id, $phone]);

            // ยืนยัน transaction
            $pdo->commit();
            $success = 'ลงทะเบียนสำเร็จ! คุณสามารถ <a href="login.html" class="alert-link">เข้าสู่ระบบ</a> ได้เลย';

            // ล้างข้อมูลฟอร์ม
            $_POST = [];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'เกิดข้อผิดพลาดในการลงทะเบียน: โปรดลองใหม่';
            // สำหรับ debug: $errors[] = 'เกิดข้อผิดพลาดในการลงทะเบียน: ' . $e->getMessage();
        }
    }
}

// ดึงข้อมูลยศและตำแหน่งสำหรับ <select>
try {
    $stmt = $pdo->query("SELECT rank_id, rank_name FROM user_rank ORDER BY rank_id");
    $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT position_id, position_name FROM user_position ORDER BY position_id");
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT id, department_name FROM master_departments WHERE status_delete = 0 ORDER BY department_name ASC");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'เกิดข้อผิดพลาดในการดึงข้อมูลยศ/ตำแหน่ง: โปรดลองใหม่';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียน - ระบบบริหารจัดการฐานข้อมูลวัตถุพยาน</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome.all.min.css"> 
	<style>
		@font-face {
            font-family: 'Sarabun';
            src: url('fonts/Sarabun/Sarabun-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            }

            @font-face {
            font-family: 'Sarabun';
            src: url('fonts/Sarabun/Sarabun-Bold.ttf') format('truetype');
            font-weight: bold;
            font-style: normal;
            }

		body,
		.form-control,
		.form-select,
		.btn,
		.alert,
		.section-header,
		h1, h2, h3, h4, h5, h6 {
			font-family: 'Sarabun', sans-serif;
		}

        :root {
            --bg: #080c14;
            --surface: #0e1420;
            --surface2: #131926;
            --accent: #00d4ff;
            --accent2: #0057ff;
            --text: #e8edf5;
            --text-muted: #8ea0c2;
            --border: rgba(0, 212, 255, 0.12);
        }

        body {
            background: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        .bg-grid {
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(0,212,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,212,255,0.04) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
        }

        .orb-1 {
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, rgba(0,87,255,0.22) 0%, transparent 70%);
            top: -120px;
            left: -120px;
        }

        .orb-2 {
            width: 340px;
            height: 340px;
            background: radial-gradient(circle, rgba(0,212,255,0.18) 0%, transparent 70%);
            bottom: -100px;
            right: -100px;
        }

        .register-container {
            position: relative;
            z-index: 5;
            max-width: 760px;
            width: 100%;
            padding: 30px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.45);
        }

        .register-header h2 {
            color: var(--text) !important;
            font-weight: 700;
        }

        .header-subtitle {
            color: var(--text-muted);
            font-size: 12px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .icon-ring {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 35%, rgba(0,212,255,0.18), rgba(0,87,255,0.06));
            border: 1px solid rgba(0,212,255,0.35);
            color: var(--accent);
            font-size: 1.3rem;
        }

        .form-label {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            padding: 10px 14px;
            background: var(--surface2);
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text);
        }

        .form-control::placeholder {
            color: #7f8da8;
        }

        .form-control:focus,
        .form-select:focus {
            background: var(--surface2);
            color: var(--text);
            border-color: rgba(0, 212, 255, 0.45);
            box-shadow: 0 0 0 0.2rem rgba(0, 212, 255, 0.12);
        }

        .password-wrap {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #9db0d1;
            cursor: pointer;
            padding: 4px;
        }

        .password-toggle:hover {
            color: var(--accent);
        }

        .btn-primary {
            background: linear-gradient(135deg, #0057ff 0%, #00b4d8 100%);
            border: none;
            border-radius: 10px;
            padding: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            filter: brightness(1.07);
            transform: translateY(-1px);
        }

        .section-header {
            background: rgba(0,212,255,0.05);
            color: var(--text);
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            border-left: 4px solid var(--accent2);
            font-weight: 600;
        }

        .form-text {
            color: #8ea0c2;
        }

        hr {
            border-color: rgba(255,255,255,0.12);
        }

        .border-top {
            border-color: rgba(255,255,255,0.12) !important;
        }

        .text-center a {
            color: var(--accent);
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="register-container">
        <div class="register-header text-center mb-4">
            <div class="icon-ring mx-auto mb-3">
                <i class="fas fa-user-plus"></i>
            </div>
            <h2 class="text-center mb-1 text-light">ลงทะเบียน</h2>
            <div class="header-subtitle">Register</div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success text-center" role="alert"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i> กรุณาแก้ไขข้อผิดพลาดดังนี้:</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="registerForm">
            
            <div class="section-header">
                <i class="fas fa-lock me-2"></i> ข้อมูลบัญชีผู้ใช้
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           placeholder="example@gmail.com" required>
                    <div class="invalid-feedback">อีเมลต้องเป็น @gmail.com เท่านั้น</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                    <div class="password-wrap">
                        <input type="password" class="form-control pe-5" id="password" name="password" required>
                        <button type="button" class="password-toggle" data-target="password" aria-label="แสดง/ซ่อนรหัสผ่าน">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
                    <div class="password-wrap">
                        <input type="password" class="form-control pe-5" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="password-toggle" data-target="confirm_password" aria-label="แสดง/ซ่อนรหัสผ่าน">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="section-header">
                <i class="fas fa-address-card me-2"></i> ข้อมูลส่วนตัวและหน่วยงาน
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name" class="form-label">ชื่อ (ภาษาไทย) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name" 
                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="last_name" class="form-label">นามสกุล (ภาษาไทย)<span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name" 
                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name_en" class="form-label">ชื่อ (ภาษาอังกฤษ)<span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name_en" name="first_name_en" 
                           value="<?php echo isset($_POST['first_name_en']) ? htmlspecialchars($_POST['first_name_en']) : ''; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="last_name_en" class="form-label">นามสกุล (ภาษาอังกฤษ)<span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name_en" name="last_name_en" 
                           value="<?php echo isset($_POST['last_name_en']) ? htmlspecialchars($_POST['last_name_en']) : ''; ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="rank_id" class="form-label">ยศ <span class="text-danger">*</span></label>
                    <select class="form-select" id="rank_id" name="rank_id" required>
                        <option value="">กรุณาเลือก</option>
                        <?php foreach ($ranks as $rank): ?>
                            <option value="<?php echo $rank['rank_id']; ?>" 
                                    <?php echo isset($_POST['rank_id']) && $_POST['rank_id'] == $rank['rank_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rank['rank_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="position_id" class="form-label">ตำแหน่ง <span class="text-danger">*</span></label>
                    <select class="form-select" id="position_id" name="position_id" required>
                        <option value="">กรุณาเลือก</option>
                        <?php foreach ($positions as $position): ?>
                            <option value="<?php echo $position['position_id']; ?>" 
                                    <?php echo isset($_POST['position_id']) && $_POST['position_id'] == $position['position_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($position['position_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="department_id" class="form-label">หน่วยงาน <span class="text-danger">*</span></label>
                    <select class="form-select" id="department_id" name="department_id" required>
                        <option value="">กรุณาเลือก</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo isset($_POST['department_id']) && $_POST['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                 <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                    <input type="text" class="form-control" id="phone" name="phone" 
                          value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                          maxlength="12" inputmode="numeric" autocomplete="off">
                      <div class="form-text">ไม่บังคับ (กรอกได้เฉพาะ 10 หลัก ระบบใส่ - อัตโนมัติ)</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">นวท.(สบ)</label>
                    <select class="form-select" id="agency_nvt" name="nvt_sub">
                        <option value="">-- กรุณาเลือก --</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="4">5</option>
                    </select>
                    <div class="form-text">ไม่บังคับ</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">ศพฐ</label>
                    <select class="form-select" id="agency_spt" name="spt_sub">
                        <option value="">-- กรุณาเลือก --</option>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="form-text">ไม่บังคับ</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">พฐจว</label>
                    <select class="form-select" id="agency_ptjv" name="ptjv_sub">
                        <option value="">-- กรุณาเลือก --</option>
                        <option value="94">ปัตตานี</option>
                        <option value="95">ยะลา</option>
                        <option value="96">นราธิวาส</option>
                        <option value="90">สงขลา</option>
                    </select>
                    <div class="form-text">ไม่บังคับ</div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mt-3">
                <i class="fas fa-check-circle me-2"></i> ลงทะเบียน
            </button>
        </form>
        
        <div class="text-center mt-4 pt-3 border-top">
            <p class="mb-0">มีบัญชีอยู่แล้ว? <a href="login.html" class="text-primary fw-bold">เข้าสู่ระบบที่นี่</a></p>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        const registerForm = document.getElementById('registerForm');
        const phoneInput = document.getElementById('phone');

        // รูปแบบเบอร์โทร: 080-000-0000
        function formatPhone(value) {
            const digits = value.replace(/\D/g, '').slice(0, 10);
            if (digits.length <= 3) return digits;
            if (digits.length <= 6) return digits.slice(0, 3) + '-' + digits.slice(3);
            return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
        }

        phoneInput.addEventListener('input', function() {
            this.value = formatPhone(this.value);
        });

        // ปุ่มแสดง/ซ่อนรหัสผ่าน
        document.querySelectorAll('.password-toggle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const target = document.getElementById(targetId);
                const icon = this.querySelector('i');
                const isPassword = target.type === 'password';
                target.type = isPassword ? 'text' : 'password';
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
            });
        });

        // ตรวจสอบรหัสผ่านและเบอร์โทรศัพท์ในฝั่ง client
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const phone = document.getElementById('phone').value.trim();
            const emailVal = document.getElementById('email').value.trim().toLowerCase();
            let isValid = true;
            
            // 1. ตรวจสอบรหัสผ่าน
            if (password !== confirmPassword) {
                alert('รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน');
                isValid = false;
            }
            
            // 2. ตรวจสอบเบอร์โทรศัพท์ (ถ้ามีการกรอก)
            if (phone && !/^\d{3}-\d{3}-\d{4}$/.test(phone)) {
                alert('เบอร์โทรศัพท์ต้องเป็นรูปแบบ 080-000-0000');
                isValid = false;
            }

            // 3. ตรวจสอบอีเมล @gmail.com
            if (!emailVal || !emailVal.endsWith('@gmail.com')) {
                alert('อีเมลต้องเป็น @gmail.com เท่านั้น');
                document.getElementById('email').classList.add('is-invalid');
                isValid = false;
            } else {
                document.getElementById('email').classList.remove('is-invalid');
            }

            if (!isValid) {
                e.preventDefault();
            }
        });

        // Restore state on page reload (PHP validation error)
        (function() {
            var saved = {
                agency_nvt: '<?php echo isset($_POST["nvt_sub"]) ? htmlspecialchars($_POST["nvt_sub"]) : ""; ?>',
                agency_spt: '<?php echo isset($_POST["spt_sub"]) ? htmlspecialchars($_POST["spt_sub"]) : ""; ?>',
                agency_ptjv: '<?php echo isset($_POST["ptjv_sub"]) ? htmlspecialchars($_POST["ptjv_sub"]) : ""; ?>'
            };
            Object.keys(saved).forEach(function(id) {
                if (saved[id]) document.getElementById(id).value = saved[id];
            });
        })();
    </script>
</body>
</html>