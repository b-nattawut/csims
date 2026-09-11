<?php
// เริ่ม Session พร้อมตั้งค่า Timeout 30 นาที
require_once __DIR__ . '/includes/session_config.php';

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

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <title><?php echo isset($title) ? $title : 'ระบบวัตถุพยาน'; ?></title>
    <?php include 'includes/head.php'; ?>

    <style>
        /* ช่อง "การตรวจพิสูจน์" แบบเลือกหลายกลุ่มงาน (ติ๊กได้บนแท็บเล็ต) */
        select.lab-unit-multi {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
        .lab-unit-picker {
            position: relative;
            min-width: 92px;
        }
        .lab-unit-picker-list {
            display: flex;
            flex-direction: column;
            gap: 2px;
            max-height: 88px;
            overflow-y: auto;
            padding: 2px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background: #fff;
        }
        .lab-unit-chip {
            display: flex;
            align-items: center;
            gap: 4px;
            margin: 0;
            padding: 2px 4px;
            font-size: 10px;
            line-height: 1.25;
            border-radius: 3px;
            cursor: pointer;
            user-select: none;
            -webkit-user-select: none;
        }
        .lab-unit-chip input {
            margin: 0;
            flex-shrink: 0;
        }
        .lab-unit-chip.is-on {
            background: #dbeafe;
            color: #1e3a8a;
            font-weight: 600;
        }
    </style>

    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>

<body class="bg-light">
    <div id="status-banner" class="connection-banner">
        <span id="banner-icon"></span>
        <span id="banner-text" class="ms-2"></span>
    </div>

    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content p-3 ">
        <?php echo $content; ?>
    </main>

    <script src="js/jquery-3.7.1.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <!-- ตัวช่วยกลางสำหรับช่อง "การตรวจพิสูจน์" (เลือกได้หลายกลุ่มงาน) -->
    <script src="js/lab-unit-multi.js?v=3"></script>

    <script src="js/sweetalert2.all.min.js"></script>
    <script src="js/signature_pad.umd.min.js"></script>
    <script src="js/jquery.mask.min.js"></script>

    <script src="js/dayjs/dayjs.min.js"></script>
    <script src="js/dayjs/dayjs-plugin-buddhistEra.js"></script>
    <script src="js/dayjs/dayjs-locale-th.js"></script>
    <script>
        // Init Day.js Global
        dayjs.extend(window.dayjs_plugin_buddhistEra);
        dayjs.locale('th');
    </script>

    <script src="js/select2.min.js"></script>
    <script>
        // Init Select2 Global Defaults
        if ($.fn.select2) {
            $.fn.select2.defaults.set("theme", "bootstrap-5");
        }
    </script>

    <script src="js/jquery.twbsPagination.min.js"></script>
    <script src="js/jquery.bootpag.min.js"></script>

    <?php if (isset($extra_scripts)) echo $extra_scripts; ?>

    <script>
        // --- 1. Global Function: Check Server Health ---
        async function checkBackendHealth() {
            try {
                const res = await fetch('/csims/api/health_check.php', {
                    method: 'HEAD',
                    cache: 'no-store'
                });
                return res.ok;
            } catch (err) {
                return false;
            }
        }

        // ตัวแปรจำสถานะล่าสุด (เพื่อไม่ให้ Banner เด้งกวนใจถ้าสถานะเดิม)
        let lastConnectionState = null;

        async function checkNetworkStatus() {
            // --- 1. อ้างอิง Element (แยก ID ชัดเจน) ---
            const navbarIconDiv = document.getElementById('connection-status');
            const banner = document.getElementById('status-banner');
            const bannerText = document.getElementById('banner-text');
            const bannerIcon = document.getElementById('banner-icon');
            const syncBtn = document.getElementById('sync-status-btn');

            if (!navbarIconDiv) return;

            // --- 2. เช็คสถานะ ---
            let isOnline = navigator.onLine;
            let isServerOk = false;

            if (isOnline) {
                isServerOk = await checkBackendHealth();
            }

            // สรุปสถานะปัจจุบัน ('online' หรือ 'offline')
            const currentStatus = (isOnline && isServerOk) ? 'online' : 'offline';

            // โค้ด HTML ของไอคอน Offline (แบบ Stack)
            const offlineIconHTML = `
                <span class="fa-stack fa-stack-custom">
                    <i class="fas fa-wifi fa-stack-1x text-white"></i>
                    <i class="fas fa-slash fa-stack-1x text-white"></i>
                </span>
            `;

            // =========================================================
            // ส่วน A: อัปเดต Navbar (ทำตลอดเวลา เพื่อให้สถานะ Realtime)
            // =========================================================
            if (currentStatus === 'online') {
                // ไอคอนปกติ
                navbarIconDiv.innerHTML = '<i class="fas fa-wifi text-white" style="font-size: 1.1rem;"></i>';
                navbarIconDiv.setAttribute('data-bs-original-title', 'ออนไลน์: เชื่อมต่อเซิร์ฟเวอร์สำเร็จ'); // Tooltip Bootstrap 5

                // ปุ่ม Sync (ถ้ามี)
                if (syncBtn && !syncBtn.classList.contains('d-none')) {
                    syncBtn.classList.remove('btn-secondary');
                    syncBtn.classList.add('btn-warning');
                    syncBtn.disabled = false;
                }
            } else {
                // ไอคอน Stack (ขีดฆ่า)
                navbarIconDiv.innerHTML = offlineIconHTML;
                navbarIconDiv.setAttribute('data-bs-original-title', 'ออฟไลน์: ขาดการเชื่อมต่อ');

                // ปุ่ม Sync (ถ้ามี)
                if (syncBtn) {
                    syncBtn.classList.remove('btn-warning');
                    syncBtn.classList.add('btn-secondary');
                }
            }

            // =========================================================
            // ส่วน B: อัปเดต Banner (ทำเฉพาะตอนสถานะเปลี่ยน)
            // =========================================================
            if (lastConnectionState !== currentStatus) {

                if (lastConnectionState === null && currentStatus === 'online') {
                    lastConnectionState = currentStatus;
                    return;
                }

                // รีเซ็ต Class
                banner.classList.remove('banner-offline', 'banner-online');

                if (currentStatus === 'offline') {
                    // --- เน็ตหลุด ---

                    banner.classList.add('banner-offline');
                    bannerIcon.innerHTML = offlineIconHTML;
                    bannerText.innerText = 'ขาดการเชื่อมต่ออินเทอร์เน็ต (Offline Mode)';

                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            banner.classList.add('banner-show'); // เลื่อนลงมา
                        });
                    });

                    // (หมายเหตุ: Offline Banner จะค้างไว้ ไม่ซ่อนเอง เพื่อเตือน User ตลอดเวลา)

                } else {
                    // --- เน็ตมา ---
                    banner.classList.add('banner-online');
                    bannerIcon.innerHTML = '<i class="fas fa-wifi"></i>';
                    bannerText.innerText = 'เชื่อมต่ออินเทอร์เน็ตแล้ว';

                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            banner.classList.add('banner-show');
                        });
                    });

                    setTimeout(() => {
                        banner.classList.remove('banner-show');
                    }, 3000);
                }

                // จำสถานะล่าสุดไว้
                lastConnectionState = currentStatus;
            }
        }

        // --- 3. อัปเดตตัวเลขคิวรอส่ง (รวมทุกคิว) ---
        async function updateSyncUI() {
            const syncBtn = document.getElementById('sync-status-btn');
            const countSpan = document.getElementById('queue-count');

            if (!syncBtn) return;

            // อ่านข้อมูลจากทุก Queue
            const qAdd = JSON.parse(localStorage.getItem('incidentQueue')) || [];
            const qEdit = JSON.parse(localStorage.getItem('incidentEditQueue')) || [];
            const qDel = JSON.parse(localStorage.getItem('incidentDeleteQueue')) || [];
            const qSig = JSON.parse(localStorage.getItem('incidentSignatureQueue')) || [];

            // ★ อ่าน checklistQueue จาก IndexedDB (Dexie) แทน localStorage
            let checklistCount = 0;
            try {
                if (typeof db !== 'undefined' && db.checklistQueue) {
                    checklistCount = await db.checklistQueue.count();
                }
            } catch (e) {
                /* Dexie ยังไม่พร้อม */
            }

            // รวมยอด
            const total = qAdd.length + qEdit.length + qDel.length + qSig.length + checklistCount;

            if (total > 0) {
                countSpan.innerText = total;
                syncBtn.classList.remove('d-none'); // โชว์ปุ่ม

                // ปรับสีปุ่มตามสถานะเน็ต
                if (navigator.onLine) {
                    syncBtn.classList.remove('btn-secondary');
                    syncBtn.classList.add('btn-warning');
                } else {
                    syncBtn.classList.remove('btn-warning');
                    syncBtn.classList.add('btn-secondary');
                }
            } else {
                syncBtn.classList.add('d-none'); // ซ่อนปุ่มถ้าไม่มีคิว
            }
        }

        // --- 4. ฟังก์ชันกดปุ่ม Sync Manual (เรียกทุก Sync ที่มี) ---
        function triggerManualSync() {
            // เช็คว่าเน็ตพร้อมไหม
            if (!navigator.onLine) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ไม่มีอินเทอร์เน็ต',
                    text: 'กรุณาเชื่อมต่ออินเทอร์เน็ตก่อนทำการ Sync',
                    timer: 2000
                });
                return;
            }

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'กำลังตรวจสอบข้อมูล...',
                showConfirmButton: false,
                timer: 1000
            });

            // ตรวจสอบและเรียกใช้ Function Sync ของแต่ละหน้า (ถ้ามี)
            // ใช้ typeof เพื่อป้องกัน Error ถ้าหน้านั้นไม่มีฟังก์ชันนี้
            if (typeof syncIncidentQueue === 'function') syncIncidentQueue();
            if (typeof syncEditQueue === 'function') syncEditQueue();
            if (typeof syncDeleteQueue === 'function') syncDeleteQueue();
            if (typeof syncSignatureQueue === 'function') syncSignatureQueue();
            if (typeof syncChecklistQueue === 'function') syncChecklistQueue();

            // อัปเดต UI หลังจากเรียก Sync (เผื่อมันเร็วมาก)
            setTimeout(updateSyncUI, 1000);
        }

        // --- Init Logic ---
        document.addEventListener('DOMContentLoaded', function() {
            checkNetworkStatus();
            updateSyncUI();

            // Heartbeat เช็คเน็ตทุก 10 วิ
            setInterval(() => {
                checkNetworkStatus();
                updateSyncUI(); // เช็คคิวด้วยเผื่อมีการเปลี่ยนแปลง
            }, 10000);

            // Sidebar Toggle Logic
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
                    document.body.classList.add('sb-collapsed');
                }
                sidebarToggle.addEventListener('click', event => {
                    event.preventDefault();
                    document.body.classList.toggle('sb-collapsed');
                    localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-collapsed'));
                });
            }
        });

        // Listen to Browser Events
        window.addEventListener('online', () => {
            checkNetworkStatus();
            // ลอง Trigger Sync อัตโนมัติเมื่อเน็ตมา (Optional)
            triggerManualSync();
        });
        window.addEventListener('offline', checkNetworkStatus);
    </script>

    <script>
        // ===== Session Timeout: เด้ง popup เมื่อหมดเวลา แล้วบังคับเข้าสู่ระบบใหม่ =====
        (function () {
            var SESSION_API = '/csims/api/session_status.php';
            var POLL_INTERVAL = 60000;   // เช็คสถานะทุก 60 วินาที
            var pollTimer = null;
            var popupShown = false;
            // ★ จับ "การใช้งานจริง" ของผู้ใช้ เพื่อต่ออายุ session ให้อัตโนมัติ
            //   ป้องกันอาการหลุดออกจากระบบขณะยังทำงานอยู่ (พิมพ์ฟอร์ม/วาดแผนผัง)
            var userActive = true;
            ['mousedown', 'mousemove', 'keydown', 'touchstart', 'pointerdown', 'wheel', 'scroll', 'input', 'change']
                .forEach(function (ev) {
                    document.addEventListener(ev, function () { userActive = true; }, { passive: true, capture: true });
                });

            function goLogout() {
                clearInterval(pollTimer);
                window.location.href = '/csims/logout.php';
            }

            function showWarning() {
                if (popupShown || typeof Swal === 'undefined') return;
                popupShown = true;

                Swal.fire({
                    icon: 'warning',
                    title: 'หมดเวลาการใช้งาน',
                    html: 'เซสชันของคุณหมดเวลาแล้ว<br>กรุณาเข้าสู่ระบบใหม่อีกครั้ง',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#dc3545',
                    showCancelButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(function () {
                    // ปุ่มเดียว -> บังคับออกไปหน้า login เพื่อเข้าสู่ระบบใหม่
                    goLogout();
                });
            }

            function poll() {
                // มีการใช้งานจริงตั้งแต่รอบก่อน -> ต่ออายุ session
                // ไม่มีการใช้งาน -> แค่เช็คสถานะ (ปล่อยให้นับเวลา idle ต่อไป)
                var action = userActive ? 'heartbeat' : 'check';
                userActive = false;

                fetch(SESSION_API + '?action=' + action, { cache: 'no-store', credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        // เด้ง popup เฉพาะเมื่อ session หมดเวลาแล้วเท่านั้น (บังคับ login ใหม่)
                        if (!d || d.active === false) {
                            if (!popupShown) showWarning();
                        }
                    })
                    .catch(function () { /* เน็ตหลุดชั่วคราว ข้ามรอบนี้ */ });
            }

            document.addEventListener('DOMContentLoaded', function () {
                poll();
                pollTimer = setInterval(poll, POLL_INTERVAL);
            });
            // กลับมาที่แท็บ -> ต่ออายุทันที ไม่ต้องรอครบรอบ
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden && !popupShown) { userActive = true; poll(); }
            });
        })();
    </script>

    <script src="js/dexie.js"></script>
    <script src="js/offline-db.js"></script>
    <!-- แปลงช่องเลือกชื่อคน (ผู้บันทึก/ผู้รับมอบ/ผู้ทบทวน ฯลฯ) ให้พิมพ์ชื่อเองได้ -->
    <script src="js/free-text-names.js"></script>

    <script>
        // ลงทะเบียน Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./service-worker.js')
                    .then(reg => console.log('Service Worker พร้อมใช้งานแล้ว'))
                    .catch(err => console.log('การลงทะเบียนล้มเหลว:', err));
            });
        }
    </script>

    <div class="modal fade" id="hwModal" tabindex="-1" data-bs-backdrop="static" style="z-index:1070;">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">
                <div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#8b5cf6); border:none;">
                    <h5 class="modal-title text-white"><i class="fas fa-pen-fancy me-2"></i>เขียนด้วยลายมือ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-muted mb-0">ภาษา:</label>
                            <select class="form-select form-select-sm" id="hwLang" style="width:130px;">
                                <option value="th" selected>ไทย</option>
                                <option value="en">English</option>
                                <option value="th,en">ไทย + English</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-muted mb-0">ขนาดปากกา:</label>
                            <div class="btn-group btn-group-sm hw-pen-size-group">
                                <button type="button" class="btn btn-outline-secondary" data-size="2">S</button>
                                <button type="button" class="btn btn-outline-secondary active" data-size="4">M</button>
                                <button type="button" class="btn btn-outline-secondary" data-size="7">L</button>
                            </div>
                        </div>
                    </div>
                    <div id="hwCanvasArea" class="mb-3">
                        <canvas id="hwCanvas" width="660" height="250"></canvas>
                        <div class="hw-canvas-placeholder" id="hwPlaceholder"><i class="fas fa-pen-alt me-2"></i>เขียนตัวอักษรที่นี่...</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-primary btn-sm" id="btnHwRecognize"><i class="fas fa-magic me-1"></i>แปลงเป็นข้อความ</button>
                        <button type="button" class="btn btn-outline-warning btn-sm" id="btnHwUndo"><i class="fas fa-undo me-1"></i>Undo</button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnHwErase"><i class="fas fa-eraser me-1"></i>ลบทั้งหมด</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnHwSpace"><i class="fas fa-arrows-alt-h me-1"></i>เว้นวรรค</button>
                    </div>
                    <div class="small text-muted mb-2" id="hwStatus"></div>
                    <div id="hwResults" class="mb-3">
                        <p class="text-muted small mb-0"><i class="fas fa-arrow-down me-1"></i>ผลลัพธ์จะแสดงที่นี่หลังกด "แปลงเป็นข้อความ"</p>
                    </div>
                    <hr>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted mb-0 text-nowrap">ข้อความสะสม:</label>
                        <input type="text" class="form-control form-control-sm" id="hwAccumulated" readonly style="background:#f9fafb; font-size:1.1rem;">
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnHwClearAccum" title="ล้างข้อความ"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success" id="btnHwConfirm"><i class="fas fa-check me-1"></i>ยืนยัน ใส่ข้อความ</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/handwriting.canvas.js"></script>
    <script>
        (function() {
            var hwCanvasInstance = null;
            var hwTargetIds = [];
            var hwTargetElement = null; // direct element reference for dynamic rows
            var hwAccumulatedText = '';
            var hwHasDrawn = false;
            var hwStrokeCount = 0; // นับ stroke ที่วาดจริงๆ (ไม่ใช่แค่ click)
            var hwModalInitialized = false; // flag ป้องกัน binding ซ้ำ
            var $activeHwGroup = null; // เพิ่มตัวแปรสำหรับจำช่องที่กำลังเขียน

            // เปิด modal จากปุ่มปากกา (ใช้ one-time binding หรือ check flag)
            $(document).off('click.hwOpen').on('click.hwOpen', '.btn-hw-open', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var targets = ($(this).data('hw-targets') || '').split(',').map(function(s) {
                    return s.trim();
                }).filter(Boolean);
                hwTargetIds = targets;
                // For dynamic rows without data-hw-targets, find sibling input
                hwTargetElement = null;
                if (targets.length === 0 && $(this).hasClass('btn-hw-dynamic')) {
                    // ★ ลำดับที่ 1: หา input/textarea ที่อยู่ก่อนหน้าปุ่มโดยตรง (prev sibling)
                    var $prev = $(this).prev('input[type="text"], textarea');
                    if ($prev.length) {
                        hwTargetElement = $prev[0];
                    } else {
                        // ★ ลำดับที่ 2: หาจาก container ที่ใกล้ที่สุด
                        var $row = $(this).closest('.input-group, .input-group-sm, .sevpf-si, .sevpf-fr, .fpf-fr, .fpf-si, .fpf-cg, .fpf-method-row, .fpf-photo-row, .fpf-found-row, .pepf-si, .pepf-fr, .pepf-person-card, .pepf-ed-line1, .ppf-fr, .ppf-si, .ppf-evidence-row, .ppf-trace-row, .lpf-fr, .lpf-victim-row, .tpf-fr, .tpf-vehicle-card, .bpf-fr, .bpf-cg, .bpf-i1, .bpf-i2, .bpf-body-row, div');
                        var $inp = $row.find('input[type="text"].form-control, input[type="text"].form-control-sm, input[type="text"].sevpf-inp, input[type="text"].fpf-inp, input[type="text"].fpf-inp-s, input[type="text"].pepf-inp, input[type="text"].pepf-inp-s, input[type="text"].pepf-inp-full, input[type="text"].pepf-evidence-desc-inp, input[type="text"].ppf-inp, input[type="text"].ppf-inp-s, input[type="text"].lpf-inp, input[type="text"].lpf-inp-s, input[type="text"].tpf-inp, input[type="text"].tpf-inp-s, input[type="text"].bpf-inp, input[type="text"].bpf-inp-s').first();
                        if ($inp.length) {
                            hwTargetElement = $inp[0];
                        } else {
                            // ★ ลำดับที่ 3: หา textarea ใน container หรือ sibling ถัดไป
                            var $ta = $row.find('textarea').first();
                            if (!$ta.length) $ta = $row.next('textarea');
                            if (!$ta.length) $ta = $row.nextAll('textarea').first();
                            if (!$ta.length) $ta = $row.next().find('textarea').first();
                            if (!$ta.length) $ta = $row.parent().find('textarea').first();
                            if ($ta.length) hwTargetElement = $ta[0];
                        }
                    }
                }
                hwAccumulatedText = '';
                hwHasDrawn = false;
                hwStrokeCount = 0;
                $('#hwAccumulated').val('');
                $('#hwResults').html('<p class="text-muted small mb-0"><i class="fas fa-arrow-down me-1"></i>ผลลัพธ์จะแสดงที่นี่หลังกด "แปลงเป็นข้อความ"</p>');
                $('#hwStatus').text('');
                $('#hwPlaceholder').removeClass('hidden');

                // 1. หา parent ที่เป็นกล่อง seamless และเพิ่ม class ให้สว่างค้างไว้
                var $parent = $(this).closest('.input-group-seamless');
                if ($parent.length) {
                    $parent.addClass('hw-active');
                    $activeHwGroup = $parent; // จำไว้ว่ากล่องไหนเปิดอยู่
                }

                // ใช้ Modal instance เดิมถ้ามี หรือสร้างใหม่
                var modalEl = document.getElementById('hwModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (!modal) modal = new bootstrap.Modal(modalEl);
                modal.show();
            });

            // Init modal events ครั้งเดียว (ใช้ namespace ป้องกันซ้ำ)
            if (!hwModalInitialized) {
                hwModalInitialized = true;
                var $modal = $('#hwModal');

                // Cleanup เมื่อ modal ปิด
                $modal.off('hidden.bs.modal.hw').on('hidden.bs.modal.hw', function() {
                    if (hwCanvasInstance) {
                        var cvs = hwCanvasInstance.canvas;
                        var newCanvas = cvs.cloneNode(true);
                        cvs.parentNode.replaceChild(newCanvas, cvs);
                        hwCanvasInstance = null;
                    }
                    hwStrokeCount = 0;
                    hwHasDrawn = false;

                    if ($activeHwGroup) {
                        $activeHwGroup.removeClass('hw-active');
                        $activeHwGroup = null;
                    }
                });

                // Init canvas หลัง modal shown
                $modal.off('shown.bs.modal.hw').on('shown.bs.modal.hw', function() {
                    // ลบ instance เก่าก่อนสร้างใหม่
                    if (hwCanvasInstance) {
                        var oldCvs = hwCanvasInstance.canvas;
                        var newCvs = oldCvs.cloneNode(true);
                        oldCvs.parentNode.replaceChild(newCvs, oldCvs);
                    }
                    var canvas = document.getElementById('hwCanvas');
                    var area = document.getElementById('hwCanvasArea');
                    // รีเซ็ตขนาด canvas ตาม container
                    canvas.width = area.clientWidth || 660;
                    canvas.height = 250;

                    hwCanvasInstance = new handwriting.Canvas(canvas, 4);
                    hwCanvasInstance.set_Undo_Redo(true, true);
                    hwCanvasInstance.setOptions({
                        language: $('#hwLang').val(),
                        numOfReturn: 5
                    });
                    hwCanvasInstance.setCallBack(function(results, err) {
                        $('#hwStatus').text('');
                        if (err) {
                            $('#hwResults').html('<span class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i>' + err.message + '</span>');
                            return;
                        }
                        if (!results || results.length === 0) {
                            $('#hwResults').html('<span class="text-warning small"><i class="fas fa-question-circle me-1"></i>ไม่พบผลลัพธ์ ลองเขียนใหม่</span>');
                            return;
                        }
                        var html = '<span class="small text-muted me-2">เลือกผลลัพธ์:</span>';
                        results.forEach(function(text, idx) {
                            html += '<span class="hw-result-badge' + (idx === 0 ? ' selected' : '') + '" data-text="' + $('<span>').text(text).html().replace(/"/g, '&quot;') + '">' + $('<span>').text(text).html() + '</span>';
                        });
                        $('#hwResults').html(html);
                        hwAppendText(results[0]);
                        hwStrokeCount = 0;
                        if (hwCanvasInstance) {
                            hwCanvasInstance.erase();
                            hwHasDrawn = false;
                            $('#hwPlaceholder').removeClass('hidden');
                        }
                    });
                });

                // ซ่อน placeholder เมื่อเริ่มเขียนจริง (mousemove/touchmove มีการเคลื่อนไหว)
                $modal.off('mousedown.hw touchstart.hw').on('mousedown.hw touchstart.hw', '#hwCanvas', function() {
                    $('#hwPlaceholder').addClass('hidden');
                });
                $modal.off('mousemove.hw touchmove.hw').on('mousemove.hw touchmove.hw', '#hwCanvas', function() {
                    hwStrokeCount++;
                    if (hwStrokeCount > 2) hwHasDrawn = true; // ต้องมี movement จริงๆ ถึงจะนับว่าวาด
                });

                // ปุ่มต่างๆ (bind ครั้งเดียวด้วย namespace)
                $('#btnHwRecognize').off('click.hw').on('click.hw', function() {
                    if (!hwCanvasInstance || !hwHasDrawn || hwStrokeCount < 3) {
                        $('#hwStatus').html('<span class="text-warning"><i class="fas fa-info-circle me-1"></i>กรุณาเขียนก่อนกดแปลง</span>');
                        return;
                    }
                    hwCanvasInstance.setOptions({
                        language: $('#hwLang').val(),
                        numOfReturn: 5
                    });
                    $('#hwStatus').html('<i class="fas fa-spinner fa-spin me-1"></i>กำลังแปลง...');
                    hwCanvasInstance.recognize();
                });

                $('#btnHwUndo').off('click.hw').on('click.hw', function() {
                    if (hwCanvasInstance) {
                        hwCanvasInstance.undo();
                        if (hwCanvasInstance.step.length === 0) {
                            hwHasDrawn = false;
                            hwStrokeCount = 0;
                            $('#hwPlaceholder').removeClass('hidden');
                        }
                    }
                });

                $('#btnHwErase').off('click.hw').on('click.hw', function() {
                    if (hwCanvasInstance) {
                        hwCanvasInstance.erase();
                        hwHasDrawn = false;
                        hwStrokeCount = 0;
                        $('#hwPlaceholder').removeClass('hidden');
                    }
                });

                $('#btnHwSpace').off('click.hw').on('click.hw', function() {
                    hwAccumulatedText += ' ';
                    $('#hwAccumulated').val(hwAccumulatedText);
                });

                $('#btnHwClearAccum').off('click.hw').on('click.hw', function() {
                    hwAccumulatedText = '';
                    $('#hwAccumulated').val('');
                });

                // เลือกผลลัพธ์ (delegate แต่ใช้ off ก่อน on กัน double bind)
                $(document).off('click.hwBadge').on('click.hwBadge', '#hwResults .hw-result-badge', function() {
                    var prev = $('#hwResults .hw-result-badge.selected');
                    if (prev.length) {
                        var prevText = prev.attr('data-text');
                        if (hwAccumulatedText.endsWith(prevText)) {
                            hwAccumulatedText = hwAccumulatedText.slice(0, -prevText.length);
                        }
                    }
                    $('#hwResults .hw-result-badge').removeClass('selected');
                    $(this).addClass('selected');
                    hwAppendText($(this).attr('data-text'));
                });

                // ขนาดปากกา
                $(document).off('click.hwPenSize').on('click.hwPenSize', '.hw-pen-size-group .btn', function() {
                    $('.hw-pen-size-group .btn').removeClass('active');
                    $(this).addClass('active');
                    if (hwCanvasInstance) hwCanvasInstance.setLineWidth(parseInt($(this).data('size')));
                });

                // ยืนยัน
                $('#btnHwConfirm').off('click.hw').on('click.hw', function() {
                    if (hwAccumulatedText.trim()) {
                        // Direct element reference (dynamic rows)
                        if (hwTargetElement) {
                            hwAppendToElement(hwTargetElement, hwAccumulatedText);
                        }
                        // ID-based targets (static fields)
                        if (hwTargetIds.length > 0) {
                            hwTargetIds.forEach(function(id) {
                                var el = document.getElementById(id);
                                if (el) {
                                    hwAppendToElement(el, hwAccumulatedText);
                                }
                            });
                        }
                    }
                    var modalEl = document.getElementById('hwModal');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                });
            }

            // ★ ต่อท้ายข้อความ โดยรองรับ auto-wrap multi-line group
            function hwAppendToElement(el, text) {
                var autoClass = null;
                if (el.classList) {
                    for (var i = 0; i < el.classList.length; i++) {
                        if (el.classList[i].indexOf('auto-line') !== -1) {
                            autoClass = el.classList[i];
                            break;
                        }
                    }
                }
                if (autoClass) {
                    var scope = el.closest('.modal') || el.closest('form') || document;
                    var allInputs = Array.prototype.slice.call(scope.querySelectorAll('.' + autoClass));
                    if (allInputs.length > 1) {
                        var totalText = '';
                        allInputs.forEach(function(inp) { totalText += inp.value || ''; });
                        totalText += text;
                        allInputs[0].value = totalText;
                        for (var j = 1; j < allInputs.length; j++) allInputs[j].value = '';
                        allInputs[0].dispatchEvent(new Event('input', { bubbles: true }));
                        return;
                    }
                }
                el.value = (el.value || '') + text;
                $(el).trigger('change').trigger('input');
            }

            function hwAppendText(text) {
                hwAccumulatedText += text;
                $('#hwAccumulated').val(hwAccumulatedText);
            }
        })();
    </script>
</body>

</html>