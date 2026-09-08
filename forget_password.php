<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน | CSIMS</title>
    <style>
        @font-face {
            font-family: 'Sarabun';
            src: url('fonts/Sarabun/Sarabun-Regular.ttf') format('truetype');
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #080c14;
            --surface: #0e1420;
            --surface2: #131926;
            --accent: #00d4ff;
            --accent2: #0057ff;
            --danger: #ff4d6d;
            --success: #06d6a0;
            --text: #e8edf5;
            --text-muted: #6b7a99;
            --border: rgba(0, 212, 255, 0.12);
            --glow: rgba(0, 212, 255, 0.25);
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* === BACKGROUND GRID === */
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

        /* === GLOW ORBS === */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
        }
        .orb-1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(0,87,255,0.18) 0%, transparent 70%);
            top: -150px; left: -150px;
            animation: driftA 12s ease-in-out infinite alternate;
        }
        .orb-2 {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(0,212,255,0.12) 0%, transparent 70%);
            bottom: -100px; right: -100px;
            animation: driftB 15s ease-in-out infinite alternate;
        }
        .orb-3 {
            width: 250px; height: 250px;
            background: radial-gradient(circle, rgba(255,77,109,0.08) 0%, transparent 70%);
            top: 50%; left: 60%;
            animation: driftC 18s ease-in-out infinite alternate;
        }

        @keyframes driftA { from { transform: translate(0,0); } to { transform: translate(60px, 40px); } }
        @keyframes driftB { from { transform: translate(0,0); } to { transform: translate(-50px, -30px); } }
        @keyframes driftC { from { transform: translate(0,0); } to { transform: translate(-40px, 50px); } }

        /* === SCAN LINE === */
        .scanline {
            position: fixed;
            inset: 0;
            background: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 3px,
                rgba(0,0,0,0.04) 3px,
                rgba(0,0,0,0.04) 4px
            );
            pointer-events: none;
            z-index: 1;
        }

        /* === MAIN CARD === */
        .card {
            position: relative;
            z-index: 10;
            width: 460px;
            max-width: 95vw;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 48px 44px;
            box-shadow:
                0 0 0 1px rgba(0,212,255,0.06),
                0 40px 80px rgba(0,0,0,0.6),
                0 0 60px rgba(0,87,255,0.08);
            animation: cardIn 0.7s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* === CORNER ACCENTS === */
        .card::before, .card::after {
            content: '';
            position: absolute;
            width: 24px; height: 24px;
            border-color: var(--accent);
            border-style: solid;
        }
        .card::before { top: -1px; left: -1px; border-width: 2px 0 0 2px; border-radius: 20px 0 0 0; }
        .card::after { bottom: -1px; right: -1px; border-width: 0 2px 2px 0; border-radius: 0 0 20px 0; }

        /* === HEADER === */
        .header {
            text-align: center;
            margin-bottom: 36px;
            animation: fadeUp 0.6s 0.15s both;
        }

        .icon-ring {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 35%, rgba(0,212,255,0.15), rgba(0,87,255,0.05));
            border: 1px solid rgba(0,212,255,0.3);
            margin-bottom: 20px;
            position: relative;
            box-shadow: 0 0 30px rgba(0,212,255,0.15);
        }

        .icon-ring::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 1px solid rgba(0,212,255,0.1);
            animation: spinRing 8s linear infinite;
            border-top-color: rgba(0,212,255,0.4);
        }

        @keyframes spinRing {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .lock-svg {
            width: 36px;
            height: 36px;
            color: var(--accent);
            filter: drop-shadow(0 0 8px rgba(0,212,255,0.5));
        }

        .system-name {
            font-family: 'Space Mono', monospace;
            font-size: 13px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--accent);
            opacity: 0.85;
            margin-bottom: 6px;
        }

        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .page-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* === DIVIDER === */
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border), transparent);
            margin-bottom: 32px;
        }

        /* === STEPS === */
        .steps {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 28px;
            animation: fadeUp 0.6s 0.2s both;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
            opacity: 0.5;
            transition: all 0.3s;
        }

        .step.active {
            color: var(--accent);
            opacity: 1;
        }

        .step.done {
            color: var(--success);
            opacity: 1;
        }

        .step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 1.5px solid currentColor;
            font-size: 11px;
            font-weight: 700;
        }

        .step.active .step-num {
            background: rgba(0,212,255,0.15);
            border-color: var(--accent);
            box-shadow: 0 0 10px rgba(0,212,255,0.2);
        }

        .step.done .step-num {
            background: rgba(6,214,160,0.15);
            border-color: var(--success);
        }

        .step-line {
            width: 30px;
            height: 1px;
            background: var(--text-muted);
            opacity: 0.3;
        }

        /* === FORM === */
        .form-group {
            margin-bottom: 22px;
            animation: fadeUp 0.6s both;
        }
        .form-group:nth-child(1) { animation-delay: 0.25s; }
        .form-group:nth-child(2) { animation-delay: 0.32s; }

        label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        label svg {
            width: 14px; height: 14px;
            color: var(--accent);
            opacity: 0.7;
        }

        .input-wrap {
            position: relative;
        }

        input[type="email"],
        input[type="text"],
        input[type="password"] {
            width: 100%;
            background: var(--surface2);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 10px;
            padding: 14px 18px;
            font-family: 'Sarabun', sans-serif;
            font-size: 15px;
            color: var(--text);
            outline: none;
            transition: border-color 0.25s, box-shadow 0.25s;
        }

        input::placeholder { color: var(--text-muted); opacity: 0.5; }

        input:focus {
            border-color: rgba(0,212,255,0.4);
            box-shadow: 0 0 0 3px rgba(0,212,255,0.08), inset 0 0 20px rgba(0,212,255,0.03);
        }

        /* OTP Input */
        .otp-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 22px;
            animation: fadeUp 0.6s 0.25s both;
        }

        .otp-input {
            width: 50px;
            height: 56px;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            font-family: 'Space Mono', 'Sarabun', monospace;
            background: var(--surface2);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 10px;
            color: var(--accent);
            outline: none;
            transition: border-color 0.25s, box-shadow 0.25s;
            padding: 0 !important;
        }

        .otp-input:focus {
            border-color: rgba(0,212,255,0.5);
            box-shadow: 0 0 0 3px rgba(0,212,255,0.12), 0 0 20px rgba(0,212,255,0.08);
        }

        /* === BUTTON === */
        .btn-primary {
            width: 100%;
            padding: 15px;
            margin-top: 8px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #0057ff 0%, #00b4d8 100%);
            color: #fff;
            font-family: 'Sarabun', sans-serif;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 24px rgba(0,87,255,0.35);
            animation: fadeUp 0.6s 0.4s both;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before { left: 100%; }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(0,87,255,0.5);
        }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* === RESEND === */
        .resend-row {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: var(--text-muted);
            animation: fadeUp 0.6s 0.45s both;
        }

        .resend-row a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }

        .resend-row a:hover { text-decoration: underline; }

        .resend-row a.disabled {
            color: var(--text-muted);
            opacity: 0.5;
            pointer-events: none;
        }

        /* === BACK LINK === */
        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 28px;
            animation: fadeUp 0.6s 0.5s both;
        }

        .back-link a {
            font-size: 13px;
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .back-link a:hover { color: var(--accent); }
        .back-link svg { width: 14px; height: 14px; }

        /* === MESSAGES === */
        .error-msg {
            color: var(--danger);
            font-size: 13px;
            text-align: center;
            margin-top: 12px;
            min-height: 20px;
        }

        .success-msg {
            color: var(--success);
            font-size: 13px;
            text-align: center;
            margin-top: 12px;
            min-height: 20px;
        }

        /* === NOTICE === */
        .notice {
            margin-top: 24px;
            padding: 12px 16px;
            border-radius: 8px;
            background: rgba(0,212,255,0.04);
            border: 1px solid rgba(0,212,255,0.1);
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
            animation: fadeUp 0.6s 0.55s both;
        }

        .notice svg {
            width: 14px; height: 14px;
            color: var(--accent);
            flex-shrink: 0;
            opacity: 0.7;
            margin-top: 2px;
        }

        /* === PASSWORD STRENGTH === */
        .pw-strength {
            display: flex;
            gap: 4px;
            margin-top: 8px;
            animation: fadeUp 0.4s both;
        }

        .pw-bar {
            flex: 1;
            height: 3px;
            border-radius: 2px;
            background: rgba(255,255,255,0.08);
            transition: background 0.3s;
        }

        .pw-bar.weak { background: var(--danger); }
        .pw-bar.medium { background: #ffd166; }
        .pw-bar.strong { background: var(--success); }

        .pw-label {
            font-size: 11px;
            margin-top: 4px;
            text-align: right;
        }

        /* === SECTIONS VISIBILITY === */
        .step-section {
            display: none;
        }

        .step-section.active {
            display: block;
            animation: fadeUp 0.5s both;
        }

        /* === SUCCESS ANIMATION === */
        .success-check {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding: 20px 0;
            animation: fadeUp 0.6s both;
        }

        .success-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(6,214,160,0.1);
            border: 2px solid var(--success);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 30px rgba(6,214,160,0.2);
            animation: scaleIn 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }

        .success-circle svg {
            width: 36px;
            height: 36px;
            color: var(--success);
        }

        @keyframes scaleIn {
            from { transform: scale(0.5); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* === Password toggle === */
        .pw-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            padding: 4px;
            display: flex;
            transition: color 0.2s;
        }
        .pw-toggle:hover { color: var(--accent); }
        .pw-toggle svg { width: 18px; height: 18px; }

        input[type="password"],
        input[type="text"] {
            padding-right: 44px;
        }
    </style>
</head>
<body>

<div class="bg-grid"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>
<div class="scanline"></div>

<div class="card">

    <div class="header">
        <div class="icon-ring">
            <svg class="lock-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                <circle cx="12" cy="16" r="1"/>
            </svg>
        </div>
        <div class="system-name">CSIMS</div>
        <div class="page-title">ลืมรหัสผ่าน</div>
        <div class="page-desc">กรอกอีเมลของคุณเพื่อรับรหัส OTP สำหรับตั้งรหัสผ่านใหม่</div>
    </div>

    <div class="divider"></div>

    <!-- Steps Indicator -->
    <div class="steps">
        <div class="step active" id="stepInd1">
            <span class="step-num">1</span>
            <span>กรอกอีเมล</span>
        </div>
        <div class="step-line"></div>
        <div class="step" id="stepInd2">
            <span class="step-num">2</span>
            <span>ยืนยัน OTP</span>
        </div>
        <div class="step-line"></div>
        <div class="step" id="stepInd3">
            <span class="step-num">3</span>
            <span>ตั้งรหัสใหม่</span>
        </div>
    </div>

    <!-- ========== STEP 1: กรอกอีเมล ========== -->
    <div class="step-section active" id="step1">
        <form id="emailForm">
            <div class="form-group">
                <label>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    อีเมลผู้ใช้งาน
                </label>
                <div class="input-wrap">
                    <input type="email" id="resetEmail" placeholder="กรอกอีเมลที่ลงทะเบียนไว้" required autocomplete="off">
                </div>
            </div>
            <button type="submit" class="btn-primary" id="btnSendOtp">ส่งรหัส OTP</button>
        </form>
        <div id="msg1" class="error-msg"></div>
    </div>

    <!-- ========== STEP 2: ยืนยัน OTP ========== -->
    <div class="step-section" id="step2">
        <form id="otpForm">
            <p style="text-align:center;font-size:13px;color:var(--text-muted);margin-bottom:20px;">
                รหัส OTP ถูกส่งไปยัง <strong id="maskedEmail" style="color:var(--accent);"></strong>
            </p>
            <div class="otp-group">
                <input type="text" class="otp-input" maxlength="1" data-idx="0" inputmode="numeric" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" data-idx="1" inputmode="numeric" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" data-idx="2" inputmode="numeric" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" data-idx="3" inputmode="numeric" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" data-idx="4" inputmode="numeric" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" data-idx="5" inputmode="numeric" autocomplete="off">
            </div>
            <button type="submit" class="btn-primary" id="btnVerifyOtp">ยืนยันรหัส OTP</button>
        </form>
        <div class="resend-row">
            ไม่ได้รับรหัส? <a href="#" id="resendLink" onclick="resendOtp(event)">ส่งอีกครั้ง</a>
            <span id="resendTimer"></span>
        </div>
        <div id="msg2" class="error-msg"></div>
    </div>

    <!-- ========== STEP 3: ตั้งรหัสผ่านใหม่ ========== -->
    <div class="step-section" id="step3">
        <form id="resetForm">
            <div class="form-group">
                <label>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    รหัสผ่านใหม่
                </label>
                <div class="input-wrap">
                    <input type="password" id="newPassword" placeholder="กรอกรหัสผ่านใหม่" required minlength="8">
                    <button type="button" class="pw-toggle" onclick="togglePw('newPassword', this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="pw-strength" id="pwStrength">
                    <div class="pw-bar" id="bar1"></div>
                    <div class="pw-bar" id="bar2"></div>
                    <div class="pw-bar" id="bar3"></div>
                    <div class="pw-bar" id="bar4"></div>
                </div>
                <div class="pw-label" id="pwLabel" style="color:var(--text-muted);"></div>
            </div>
            <div class="form-group">
                <label>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    ยืนยันรหัสผ่านใหม่
                </label>
                <div class="input-wrap">
                    <input type="password" id="confirmPassword" placeholder="กรอกรหัสผ่านอีกครั้ง" required minlength="8">
                    <button type="button" class="pw-toggle" onclick="togglePw('confirmPassword', this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-primary" id="btnResetPw">เปลี่ยนรหัสผ่าน</button>
        </form>
        <div id="msg3" class="error-msg"></div>
    </div>

    <!-- ========== STEP 4: สำเร็จ ========== -->
    <div class="step-section" id="step4">
        <div class="success-check">
            <div class="success-circle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>
            <div class="page-title" style="font-size:20px;">เปลี่ยนรหัสผ่านสำเร็จ!</div>
            <div class="page-desc">คุณสามารถใช้รหัสผ่านใหม่เข้าสู่ระบบได้ทันที</div>
            <a href="login.html" class="btn-primary" style="display:block;text-align:center;text-decoration:none;margin-top:12px;">
                ไปหน้าเข้าสู่ระบบ
            </a>
        </div>
    </div>

    <!-- Back link -->
    <div class="back-link">
        <a href="login.html">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            กลับไปหน้าเข้าสู่ระบบ
        </a>
    </div>

    <div class="notice">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        รหัส OTP จะถูกส่งไปยังอีเมลที่ลงทะเบียนไว้ กรุณาตรวจสอบกล่องจดหมายหรือ Spam/Junk folder
    </div>

</div>

<script>
var userEmail = '';
var resendCooldown = 0;
var resendInterval = null;

// ========== STEP NAVIGATION ==========
function goToStep(num) {
    document.querySelectorAll('.step-section').forEach(function(s) { s.classList.remove('active'); });
    document.getElementById('step' + num).classList.add('active');

    // Update step indicators
    for (var i = 1; i <= 3; i++) {
        var el = document.getElementById('stepInd' + i);
        el.classList.remove('active', 'done');
        if (i < num) el.classList.add('done');
        else if (i === num) el.classList.add('active');
    }

    // Update description
    var descs = {
        1: 'กรอกอีเมลของคุณเพื่อรับรหัส OTP สำหรับตั้งรหัสผ่านใหม่',
        2: 'กรอกรหัส OTP 6 หลักที่ส่งไปยังอีเมลของคุณ',
        3: 'ตั้งรหัสผ่านใหม่ของคุณ',
        4: ''
    };
    document.querySelector('.page-desc').textContent = descs[num] || '';
}

// ========== MASK EMAIL ==========
function maskEmail(email) {
    var parts = email.split('@');
    var name = parts[0];
    if (name.length <= 3) return name[0] + '***@' + parts[1];
    return name.substring(0, 3) + '***@' + parts[1];
}

// ========== STEP 1: ส่ง OTP ==========
document.getElementById('emailForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    var msg = document.getElementById('msg1');
    var btn = document.getElementById('btnSendOtp');
    msg.textContent = '';
    msg.className = 'error-msg';
    userEmail = document.getElementById('resetEmail').value.trim();

    if (!userEmail) { msg.textContent = 'กรุณากรอกอีเมล'; return; }

    btn.disabled = true;
    btn.textContent = 'กำลังส่ง...';

    try {
        var res = await fetch('api/forgot_password/send_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: userEmail })
        });
        var data = await res.json();

        if (data.success) {
            document.getElementById('maskedEmail').textContent = maskEmail(userEmail);
            goToStep(2);
            startResendCooldown(60);
            document.querySelectorAll('.otp-input')[0].focus();
        } else {
            msg.textContent = data.message || 'ไม่พบอีเมลนี้ในระบบ';
        }
    } catch (err) {
        msg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
    }

    btn.disabled = false;
    btn.textContent = 'ส่งรหัส OTP';
});

// ========== OTP INPUTS ==========
document.querySelectorAll('.otp-input').forEach(function(inp, idx, arr) {
    inp.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value && idx < arr.length - 1) arr[idx + 1].focus();
    });
    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && !this.value && idx > 0) arr[idx - 1].focus();
    });
    inp.addEventListener('paste', function(e) {
        e.preventDefault();
        var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
        for (var i = 0; i < Math.min(pasted.length, arr.length); i++) {
            arr[i].value = pasted[i];
        }
        var focusIdx = Math.min(pasted.length, arr.length - 1);
        arr[focusIdx].focus();
    });
});

// ========== STEP 2: ยืนยัน OTP ==========
document.getElementById('otpForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    var msg = document.getElementById('msg2');
    var btn = document.getElementById('btnVerifyOtp');
    msg.textContent = '';
    msg.className = 'error-msg';

    var otp = '';
    document.querySelectorAll('.otp-input').forEach(function(inp) { otp += inp.value; });

    if (otp.length < 6) { msg.textContent = 'กรุณากรอก OTP ให้ครบ 6 หลัก'; return; }

    btn.disabled = true;
    btn.textContent = 'กำลังตรวจสอบ...';

    try {
        var res = await fetch('api/forgot_password/verify_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: userEmail, otp: otp })
        });
        var data = await res.json();

        if (data.success) {
            goToStep(3);
            document.getElementById('newPassword').focus();
        } else {
            msg.textContent = data.message || 'รหัส OTP ไม่ถูกต้องหรือหมดอายุ';
        }
    } catch (err) {
        msg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
    }

    btn.disabled = false;
    btn.textContent = 'ยืนยันรหัส OTP';
});

// ========== STEP 3: ตั้งรหัสผ่านใหม่ ==========
document.getElementById('resetForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    var msg = document.getElementById('msg3');
    var btn = document.getElementById('btnResetPw');
    msg.textContent = '';
    msg.className = 'error-msg';

    var pw = document.getElementById('newPassword').value;
    var cpw = document.getElementById('confirmPassword').value;

    if (pw.length < 8) { msg.textContent = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'; return; }
    if (pw !== cpw) { msg.textContent = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน'; return; }

    btn.disabled = true;
    btn.textContent = 'กำลังเปลี่ยนรหัสผ่าน...';

    try {
        var otp = '';
        document.querySelectorAll('.otp-input').forEach(function(inp) { otp += inp.value; });

        var res = await fetch('api/forgot_password/reset_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: userEmail, otp: otp, new_password: pw })
        });
        var data = await res.json();

        if (data.success) {
            goToStep(4);
            document.querySelector('.steps').style.display = 'none';
            document.querySelector('.back-link').style.display = 'none';
        } else {
            msg.textContent = data.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        }
    } catch (err) {
        msg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
    }

    btn.disabled = false;
    btn.textContent = 'เปลี่ยนรหัสผ่าน';
});

// ========== RESEND OTP ==========
function startResendCooldown(sec) {
    resendCooldown = sec;
    var link = document.getElementById('resendLink');
    var timer = document.getElementById('resendTimer');
    link.classList.add('disabled');

    if (resendInterval) clearInterval(resendInterval);
    resendInterval = setInterval(function() {
        resendCooldown--;
        if (resendCooldown <= 0) {
            clearInterval(resendInterval);
            link.classList.remove('disabled');
            timer.textContent = '';
        } else {
            timer.textContent = ' (' + resendCooldown + ' วินาที)';
        }
    }, 1000);
    timer.textContent = ' (' + resendCooldown + ' วินาที)';
}

async function resendOtp(e) {
    e.preventDefault();
    if (resendCooldown > 0) return;
    var msg = document.getElementById('msg2');
    msg.className = 'success-msg';
    msg.textContent = 'กำลังส่ง OTP ใหม่...';

    try {
        var res = await fetch('api/forgot_password/send_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: userEmail })
        });
        var data = await res.json();
        if (data.success) {
            msg.className = 'success-msg';
            msg.textContent = 'ส่งรหัส OTP ใหม่เรียบร้อยแล้ว';
            startResendCooldown(60);
        } else {
            msg.className = 'error-msg';
            msg.textContent = data.message || 'ส่ง OTP ไม่สำเร็จ';
        }
    } catch (err) {
        msg.className = 'error-msg';
        msg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
    }
}

// ========== PASSWORD STRENGTH ==========
document.getElementById('newPassword').addEventListener('input', function() {
    var pw = this.value;
    var score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    var bars = ['bar1', 'bar2', 'bar3', 'bar4'];
    var labels = ['', 'อ่อนมาก', 'พอใช้', 'ดี', 'แข็งแรง'];
    var colors = ['', 'weak', 'medium', 'medium', 'strong'];
    var labelColors = ['', 'var(--danger)', '#ffd166', '#ffd166', 'var(--success)'];

    bars.forEach(function(id, i) {
        var el = document.getElementById(id);
        el.className = 'pw-bar';
        if (i < score) el.classList.add(colors[score]);
    });

    var label = document.getElementById('pwLabel');
    label.textContent = pw ? labels[score] : '';
    label.style.color = pw ? labelColors[score] : '';
});

// ========== PASSWORD TOGGLE ==========
function togglePw(inputId, btn) {
    var input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
}
</script>

</body>
</html>
