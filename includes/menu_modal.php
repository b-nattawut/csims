<?php $current_role = $_SESSION['role'] ?? ''; ?>

<div class="floating-menu d-none d-lg-block">
    <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
        data-bs-toggle="modal" data-bs-target="#moduleModal" title="เมนูหลัก" id="openModalButton">
        <i class="fas fa-th-large"></i>
    </button>
</div>

<div class="modal fade" id="moduleModal" tabindex="-1" aria-labelledby="moduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">

            <div class="modal-header py-3">
                <button type="button" class="btn btn-link btn-back text-white me-3 text-decoration-none" style="display: none;">
                    <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
                </button>
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fas fa-th-large me-2"></i>เมนูโมดูลระบบ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light">
                <div class="modal-body-wrapper">

                    <div class="modal-screen" id="mainMenuScreen">
                        <h5 class="text-secondary fw-bold mb-4 text-center opacity-75">เลือกกลุ่มเมนูหลัก</h5>
                        <div class="row g-3 justify-content-center mb-3">

                            <div class="col-6 col-md-4">
                                <div class="card menu-card h-100 main-menu-btn" data-menu-id="case-management" tabindex="0">
                                    <div class="card-body">
                                        <i class="fas fa-bell menu-card-icon" style="color: var(--info-color);"></i>
                                        <p class="menu-card-title">1. การจัดการคดี</p>
                                        <p class="menu-card-subtitle">รับแจ้งเหตุและรายงาน</p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-md-4">
                                <div class="card menu-card h-100 main-menu-btn" data-menu-id="scene-checklist" tabindex="0">
                                    <div class="card-body">
                                        <i class="fas fa-map-marked-alt menu-card-icon" style="color: var(--success-color);"></i>
                                        <p class="menu-card-title">2. Checklist สถานที่เกิดเหตุ</p>
                                        <p class="menu-card-subtitle">ตรวจเก็บตามประเภทคดี</p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-md-4">
                                <div class="card menu-card h-100 main-menu-btn" data-menu-id="evidence-lab" tabindex="0">
                                    <div class="card-body">
                                        <i class="fas fa-flask menu-card-icon" style="color: var(--danger-color);"></i>
                                        <p class="menu-card-title">3. วัตถุพยาน/ห้องปฏิบัติการ</p>
                                        <p class="menu-card-subtitle">ของกลางและการตรวจพิสูจน์</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 justify-content-center">

                            <div class="col-6 col-md-4">
                                <div class="card menu-card h-100 main-menu-btn" data-menu-id="resources-staff" tabindex="0">
                                    <div class="card-body">
                                        <i class="fas fa-tools menu-card-icon" style="color: var(--primary-color);"></i>
                                        <p class="menu-card-title">4. ทรัพยากรและเจ้าหน้าที่</p>
                                        <p class="menu-card-subtitle">จัดการเครื่องมือและบุคลากร</p>
                                    </div>
                                </div>
                            </div>

                            <?php if ($current_role === 'admin'): ?>
                            <div class="col-6 col-md-4">
                                <div class="card menu-card h-100 main-menu-btn menu-card-5" data-menu-id="user-management" tabindex="0">
                                    <div class="card-body">
                                        <i class="fas fa-user-shield menu-card-icon"></i>
                                        <p class="menu-card-title">5. ข้อมูลผู้ใช้งาน</p>
                                        <p class="menu-card-subtitle">ตั้งค่าสิทธิ์และบัญชีผู้ใช้</p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="col-6 col-md-4">
                                <a href="logout.php" class="card menu-card h-100 logout-card text-decoration-none">
                                    <div class="card-body">
                                        <i class="fas fa-sign-out-alt menu-card-icon"></i>
                                        <p class="menu-card-title">ออกจากระบบ</p>
                                        <p class="menu-card-subtitle opacity-75">Logout</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="modal-screen d-none" id="subMenuScreen">
                        <h4 class="text-primary mb-4 fw-bold" id="subMenuTitle"></h4>
                        <div class="row g-3" id="submenuContent">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-light text-secondary border px-4 rounded-pill" id="modalFooterButton" data-bs-dismiss="modal">ปิดเมนู</button>
            </div>
        </div>
    </div>
</div>

<script>
    const subMenus = {
        'case-management': {
            title: 'การจัดการคดีและการแจ้งเหตุ',
            icon: 'fas fa-bell',
            items: [{
                    href: "incident.php",
                    icon: "fas fa-gavel",
                    title: "รับแจ้งเหตุ",
                    subtitle: "บันทึกข้อมูลคดี"
                },
                {
                    href: "draft_report.php",
                    icon: "fas fa-pencil-alt",
                    title: "ร่างรายงาน",
                    subtitle: "ร่างรายงานการตรวจ"
                },
                {
                    href: "report.php",
                    icon: "fas fa-file-alt",
                    title: "รายงานฉบับสมบูรณ์",
                    subtitle: "จัดการรายงาน"
                },
                {
                    href: "barcode.php",
                    icon: "fas fa-barcode",
                    title: "เชื่อมโยงบาร์โค้ด",
                    subtitle: "เชื่อมโยงวัตถุพยาน"
                }
            ]
        },
        'scene-checklist': {
            title: 'Checklist การตรวจสถานที่เกิดเหตุ',
            icon: 'fas fa-map-marked-alt',
            items: [
                {
                    href: "incidentChecklist.php",
                    icon: "fas fa-clipboard-list", 
                    title: "รายการทั้งหมด",
                    subtitle: "จัดการรายการ Checklist ทั้งหมด"
                },
                {
                    href: "checklist_property.php",
                    icon: "fas fa-gem",
                    title: "คดีทรัพย์",
                    subtitle: "ตรวจเก็บสถานที่เกิดเหตุทรัพย์"
                },
                {
                    href: "checklist_life.php",
                    icon: "fas fa-heartbeat",
                    title: "คดีชีวิต",
                    subtitle: "ตรวจเก็บสถานที่เกิดเหตุชีวิต"
                },
                {
                    href: "checklist_explosion.php",
                    icon: "fas fa-bomb",
                    title: "คดีระเบิด",
                    subtitle: "ตรวจเก็บสถานที่เกิดเหตุระเบิด"
                },
                {
                    href: "checklist_fire.php",
                    icon: "fas fa-fire-extinguisher",
                    title: "คดีเพลิงไหม้",
                    subtitle: "ตรวจเก็บสถานที่เกิดเหตุเพลิงไหม้"
                },
                {
                    href: "checklist_traffic.php",
                    icon: "fas fa-traffic-light",
                    title: "คดีจราจร",
                    subtitle: "ตรวจเก็บสถานที่เกิดเหตุจราจร"
                }
            ]
        },
        'evidence-lab': {
            title: 'การจัดการวัตถุพยานและห้องปฏิบัติการ',
            icon: 'fas fa-flask',
            items: [{
                    href: "evidence_submission.php",
                    icon: "fas fa-box-open",
                    title: "ของกลางเพื่อตรวจพิสูจน์",
                    subtitle: "บันทึกของกลางส่งตรวจ"
                },
                {
                    href: "evidence_collection.php",
                    icon: "fas fa-clipboard-check",
                    title: "ตรวจเก็บและส่งมอบวัตถุพยาน",
                    subtitle: "จัดการการตรวจเก็บ"
                },
                {
                    href: "checklist_evidence.php",
                    icon: "fas fa-microscope",
                    title: "Checklist วัตถุพยาน",
                    subtitle: "ตรวจเก็บวัตถุพยานทั่วไป"
                },
                {
                    href: "checklist_person.php",
                    icon: "fas fa-user-check",
                    title: "Checklist วัตถุพยานบุคคล",
                    subtitle: "ตรวจเก็บวัตถุพยานบุคคล"
                },
                {
                    href: "chemical_test.php",
                    icon: "fas fa-flask",
                    title: "ทดสอบสารเคมีทั่วไป",
                    subtitle: "บันทึกผลการทดสอบสารเคมี"
                },
                {
                    href: "fingerprint_chemical.php",
                    icon: "fas fa-hand-lizard",
                    title: "ทดสอบสารเคมีลายนิ้วมือ",
                    subtitle: "บันทึกการทดสอบสารเคมี"
                }
            ]
        },
        'resources-staff': {
            title: 'การจัดการทรัพยากรและเจ้าหน้าที่',
            icon: 'fas fa-tools',
            items: [{
                    href: "officer_check.php",
                    icon: "fas fa-users",
                    title: "ตรวจจำนวนเจ้าหน้าที่",
                    subtitle: "ความพร้อมของเจ้าหน้าที่"
                },
                {
                    href: "officer_evaluation.php",
                    icon: "fas fa-user-graduate",
                    title: "ประเมินความสามารถ",
                    subtitle: "บันทึกการประเมิน"
                },
                {
                    href: "equipment_inventory.php",
                    icon: "fas fa-box",
                    title: "บัญชีเครื่องมือ",
                    subtitle: "บันทึกเครื่องมือตรวจพิสูจน์"
                },
                {
                    href: "equipment_history.php",
                    icon: "fas fa-history",
                    title: "ประวัติเครื่องมือ",
                    subtitle: "บันทึกการใช้งาน"
                },
                {
                    href: "maintenance_plan.php",
                    icon: "fas fa-cogs",
                    title: "ซ่อมบำรุง/สอบเทียบ",
                    subtitle: "แผนซ่อมบำรุง"
                },
                {
                    href: "temperature_control.php",
                    icon: "fas fa-thermometer-half",
                    title: "ควบคุมอุณหภูมิ",
                    subtitle: "บันทึกข้อมูลอุณหภูมิ"
                },
                {
                    href: "computer_usage.php",
                    icon: "fas fa-laptop",
                    title: "ใช้งานคอมพิวเตอร์",
                    subtitle: "บันทึกการใช้งาน"
                },
                {
                    href: "general_equipment.php",
                    icon: "fas fa-wrench",
                    title: "ใช้งานเครื่องมือทั่วไป",
                    subtitle: "บันทึกการใช้งาน"
                },
                {
                    href: "/gps_tracker/tracker.php",
                    icon: "fas fa-car-side",
                    title: "ติดตามรถ",
                    subtitle: "บันทึกการเดินทาง"
                }
            ]
        },
        <?php if ($current_role === 'admin'): ?>
        'user-management': {
            title: 'การจัดการข้อมูลผู้ใช้งาน',
            icon: 'fas fa-user-shield',
            items: [
                {
                    href: "manage_user.php",
                    icon: "fas fa-user-tag",
                    title: "ผู้ใช้งานและรหัสผ่าน",
                    subtitle: "เพิ่ม/แก้ไขบัญชีผู้ใช้"
                },
                {
                    href: "user_permission.php",
                    icon: "fas fa-lock",
                    title: "จำกัดสิทธิ์การใช้งาน",
                    subtitle: "กำหนดบทบาทและสิทธิ์"
                },
                {
                    href: "user_profile.php",
                    icon: "fas fa-address-card",
                    title: "ข้อมูลผู้ใช้งาน",
                    subtitle: "จัดการข้อมูลส่วนตัว"
                }
            ]
        }
        <?php endif; ?>
    };

    const mainMenuScreen = document.getElementById('mainMenuScreen');
    const subMenuScreen = document.getElementById('subMenuScreen');
    const subMenuTitle = document.getElementById('subMenuTitle');
    const submenuContent = document.getElementById('submenuContent');
    const modalTitle = document.getElementById('modalTitle');
    const modalFooterButton = document.getElementById('modalFooterButton');
    const backButton = document.querySelector('.btn-back');

    // --- LOGIC การสลับหน้าจอ Modal ---

    function showScreen(screenId) {
        if (screenId === 'mainMenuScreen') {
            mainMenuScreen.classList.remove('d-none');
            subMenuScreen.classList.add('d-none');

            modalTitle.innerHTML = '<i class="fas fa-th-large me-2"></i>เมนูโมดูลระบบ';
            modalFooterButton.textContent = 'ปิดเมนู';
            backButton.style.display = 'none';

        } else if (screenId === 'subMenuScreen') {
            mainMenuScreen.classList.add('d-none');
            subMenuScreen.classList.remove('d-none');

            modalTitle.textContent = subMenuTitle.textContent;
            modalFooterButton.textContent = 'ปิดเมนู';
            backButton.style.display = 'inline-block';
        }
    }

    function loadSubmenu(menuId) {
        const menuData = subMenus[menuId];
        if (!menuData) return;

        // 1. ตั้งชื่อเมนูย่อย
        modalTitle.innerHTML = `<i class="${menuData.icon} me-2 text-white"></i> เมนูย่อย: ${menuData.title}`;
        subMenuTitle.innerHTML = `<i class="${menuData.icon} me-2 text-primary"></i> เมนูย่อย: ${menuData.title}`;

        // 2. สร้าง HTML สำหรับเมนูย่อย
        let htmlContent = '';
        menuData.items.forEach(item => {
            htmlContent += `
                <div class="col-md-4 col-6">
                    <a href="${item.href}" class="card menu-card h-100 text-decoration-none"> 
                        <div class="card-body">
                            <i class="${item.icon} menu-card-icon"></i>
                            <p class="menu-card-title">${item.title}</p>
                            <p class="menu-card-subtitle">${item.subtitle}</p>
                        </div>
                    </a>
                </div>
            `;
        });
        submenuContent.innerHTML = htmlContent;

        // 3. แสดงหน้าจอเมนูย่อย
        showScreen('subMenuScreen');
    }

    // Event Listener สำหรับปุ่มเมนูหลัก (Main Menu)
    document.querySelectorAll('.main-menu-btn').forEach(button => {
        button.addEventListener('click', function() {
            const targetMenu = this.getAttribute('data-menu-id');
            loadSubmenu(targetMenu);
        });
    });

    // Event Listener สำหรับปุ่มย้อนกลับ (Back Button)
    backButton.addEventListener('click', function() {
        showScreen('mainMenuScreen');
    });

    // Event Listener เมื่อ Modal ถูกเปิด
    document.getElementById('moduleModal').addEventListener('show.bs.modal', function() {
        showScreen('mainMenuScreen');
    });
</script>