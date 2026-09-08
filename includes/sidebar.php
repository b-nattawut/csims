<?php
// ค้นหา + กำหนดชื่อไฟล์ปัจจุบันเพื่อใช้ทำ Active Menu แสดงผลเมนูที่กำลังเลือกอยู่ในปัจจุบัน
$current_page = basename($_SERVER['PHP_SELF']);
$current_role = strtolower(trim($_SESSION['role'] ?? ''));

// โหลด permission helper (ถ้ายังไม่ได้โหลด)
if (!function_exists('hasPermission')) {
    require_once __DIR__ . '/check_permission.php';
}

// Map: ไฟล์ PHP → permission ที่ต้องมี (.view)
$pagePermissionMap = [
    // การจัดการคดี
    'incident.php'                      => 'incident.view',
    'incidentDetail.php'                => 'incident.view',
    'incidentChecklist.php'             => 'checklist.view',
    'incidentReport.php'                => 'incident_report.view',
    'incidentReportReview.php'          => 'incident_report.view',
    'incidentReportReviewDetail.php'    => 'incident_report.view',
    'report_extension.php'              => 'report_extension.view',
    'field_visit_log.php'               => 'field_visit_log.view',
    'field_visit_log_detail.php'        => 'field_visit_log.view',
    // แดชบอร์ด (ไม่ต้องเช็คสิทธิ์)
    // 'dashboard.php'                     => 'dashboard.view',
    // จัดการข้อมูลห้องปฏิบัติการ
    'trans_equipment_usage.php'         => 'equipment_usage.view',
    'trans_computer_usage.php'          => 'computer_usage.view',
    'trans_maintenance_plan.php'        => 'maintenance.view',
    'trans_equipment_log.php'           => 'maintenance.view',
    'trans_temperature_record.php'      => 'temperature.view',
    'chemical_inventory.php'            => 'chemical.view',
    'trans_chemical_preparation.php'    => 'chemical.view',
    'trans_chemical_validation.php'     => 'chemical.view',
    'trans_chemical_validation_detail.php' => 'chemical.view',
    'trans_latent_chemical_test.php'     => 'chemical.view',
    'trans_latent_chemical_test_detail.php' => 'chemical.view',
    'trans_readiness_check.php'         => 'readiness.view',
    'trans_readiness_check_detail.php'  => 'readiness.view',
    'trans_staff_assessment.php'        => 'staff_assessment.view',
    'trans_staff_assessment_detail.php' => 'staff_assessment.view',
    // Master Data
    'master_departments.php'            => 'master_data.view',
    'master_equipment_list.php'         => 'master_data.view',
    'master_computer_list.php'          => 'master_data.view',
    'master_temperature_device.php'     => 'master_data.view',
    'master_chemical_list.php'          => 'master_data.view',
];

$menu_structure = [
    'dashboard' => [
        'title' => 'หน้าหลัก',
        'subtitle' => 'ภาพรวมระบบ',
        'icon' => 'fas fa-home',
        'color' => 'bg-primary',
        'href' => 'dashboard.php',
        'items' => []
    ],
    // 2. การจัดการคดี (รวม รับแจ้งเหตุ และ Checklist)
    'case-management' => [
        'title'    => 'การจัดการคดี',
        'subtitle' => 'รับแจ้งเหตุและตรวจสอบที่เกิดเหตุ',
        'icon'     => 'fas fa-briefcase',
        'color'    => 'bg-info',
        'items'    => [
            [
                'href'      => 'incident.php',
                'title'     => 'รับแจ้งเหตุ',
                'active_on' => ['incident.php', 'incidentDetail.php']
            ],
            [
                'href'      => 'incidentChecklist.php',
                'title'     => 'แบบตรวจเก็บ',
                'active_on' => ['incidentChecklist.php']
            ],
            [
                'href'      => 'incidentReport.php',
                'title'     => 'ร่างรายงาน',
                'active_on' => ['incidentReport.php']
            ],
           
             [
                 'href'      => 'incidentReportReview.php',
                'title'     => 'การตรวจร่างรายงาน',
                 'active_on' => ['incidentReportReview.php', 'incidentReportReviewDetail.php']
             ],
            [
                'href'      => 'report_extension.php',
                'title'     => 'ขอขยายเวลาออกรายงาน',
                'active_on' => ['report_extension.php']
            ],
            [
                'href'      => 'field_visit_log.php',
                'title'     => 'แนบเอกสารรายงานภาคสนาม',
                'active_on' => ['field_visit_log.php', 'field_visit_log_detail.php']
            ]
        ]
    ],

    // =========================================================================
    // ความพร้อมเจ้าหน้าที่ 
    // =========================================================================
    'staff-readiness' => [
        'title'    => 'ความพร้อมเจ้าหน้าที่',
        'subtitle' => 'บันทึกความพร้อมและประเมินผลการทำงาน',
        'icon'     => 'fas fa-clipboard-check',
        'color' => 'bg-danger',
        'items'    => [
            [
                'href'      => 'trans_readiness_check.php',
                'title'     => 'บันทึกจำนวนและความพร้อมเจ้าหน้าที่',
                'active_on' => ['trans_readiness_check.php', 'trans_readiness_check_detail.php'],
            ],
            [
                'href'      => 'trans_staff_assessment.php',
                'title'     => 'แบบประเมินความสามารถเจ้าหน้าที่',
                'active_on' => ['trans_staff_assessment.php', 'trans_staff_assessment_detail.php']
            ],
        ]
    ],

    // -------------------------------------------------------------------------
    // 3. ส่วนบันทึกการปฏิบัติงาน (Transaction / Operation)
    // -------------------------------------------------------------------------
    'transaction-data' => [
        'title'    => 'จัดการข้อมูลห้องปฏิบัติการ',
        'subtitle' => 'บันทึกผลการดำเนินงานประจำวัน',
        'icon'     => 'fas fa-clipboard-list',
        'color'    => 'bg-success',
        'items'    => [
            [
                // การบันทึกการใช้งานเครื่องมือทั่วไป (เช่น เครื่องแก้ว, ตาชั่ง)
                'href'      => 'trans_equipment_usage.php',
                'title'     => 'บันทึกการใช้งานเครื่องมือทั่วไป',
                'active_on' => ['trans_equipment_usage.php'],
            ],
            [
                // การบันทึกการเข้าใช้งานคอมพิวเตอร์
                'href'      => 'trans_computer_usage.php',
                'title'     => 'บันทึกการใช้งานเครื่องคอมพิวเตอร์',
                'active_on' => ['trans_computer_usage.php'],
            ],
            [
                // การบันทึกแผนการซ่อมบำรุง/สอบเทียบ (Planning/Schedule)
                'href'      => 'trans_maintenance_plan.php',
                'title'     => 'แผนการซ่อมบำรุง/สอบเทียบประจำปี',
                'active_on' => ['trans_maintenance_plan.php']
            ],
            [
                // การบันทึกประวัติการซ่อมบำรุง/สอบเทียบ ของเครื่องมือหลัก
                'href'      => 'trans_equipment_log.php',
                'title'     => 'บันทึกการซ่อมบำรุง/สอบเทียบ',
                'active_on' => ['trans_equipment_log.php']
            ],
            [
                // การจดบันทึกอุณหภูมิประจำวัน
                'href'      => 'trans_temperature_record.php',
                'title'     => 'บันทึกการควบคุมอุณหภูมิ',
                'active_on' => ['trans_temperature_record.php'],
            ],
            [
                // จัดการคลังและล็อต (ต้องมีของก่อนถึงจะทำอย่างอื่นได้)
                'href'      => 'chemical_inventory.php',
                'title'     => 'คลังและล็อตสารเคมี',
                'active_on' => ['chemical_inventory.php'],
            ],
            [
                // แปรรูป/ผสม/เตรียมสารเคมี
                'href'      => 'trans_chemical_preparation.php',
                'title'     => 'การเตรียมสารเคมี',
                'active_on' => ['trans_chemical_preparation.php'],
            ],
            [
                // การบันทึกการทดสอบสารเคมีในการตรวจสถานที่เกิดเหตุ
                'href'      => 'trans_chemical_validation.php',
                'title'     => 'บันทึกการทดสอบสารเคมี',
                'active_on' => ['trans_chemical_validation.php', 'trans_chemical_validation_detail.php'],
            ],
            [
                // การบันทึกการตรวจความพร้อมสารเคมีในการตรวจหารอยลายนิ้วมือแฝง
                'href'      => 'trans_latent_chemical_test.php',
                'title'     => 'บันทึกการตรวจความพร้อมสารเคมี',
                'active_on' => ['trans_latent_chemical_test.php', 'trans_latent_chemical_test_detail.php'],
            ],
        ]
    ],

    // -------------------------------------------------------------------------
    // 4. ส่วนจัดการข้อมูลหลัก (Master Data / Setup)
    // -------------------------------------------------------------------------
    'master-data' => [
        'title'    => 'Master Data',
        'subtitle' => 'เพิ่ม/แก้ไข รายการทรัพย์สิน',
        'icon'     => 'fas fa-database',
        'color'    => 'bg-dark',
        'items'    => [
            [
                // จัดการหน่วยงาน 
                'href'      => 'master_departments.php',
                'title'     => 'หน่วยงาน',
                'active_on' => ['master_departments.php'],
            ],
            [
                // จัดการประเภทเครื่องมือ (Category)
                'href'      => 'master_equipment_category.php',
                'title'     => 'ประเภทเครื่องมือ',
                'active_on' => ['master_equipment_category.php']
            ],
            [
                // ทะเบียนเครื่องมือหลัก (เพิ่มเครื่องใหม่, แก้ไข Serial No.)
                'href'      => 'master_equipment_list.php',
                'title'     => 'เครื่องมือ',
                'active_on' => ['master_equipment_list.php']
            ],
            [
                // ทะเบียนเครื่องคอมพิวเตอร์
                'href'      => 'master_computer_list.php',
                'title'     => 'เครื่องคอมพิวเตอร์',
                'active_on' => ['master_computer_list.php'],
            ],
            [
                // ทะเบียนเครื่องวัดอุณหภูมิ
                'href'      => 'master_temperature_device.php',
                'title'     => 'เครื่องวัดอุณหภูมิ',
                'active_on' => ['master_temperature_device.php']
            ],
            // ทะเบียนสารเคมี
            [
                'href'      => 'master_chemical_list.php',
                'title'     => 'สารเคมี',
                'active_on' => ['master_chemical_list.php'],
            ],
        ]
    ],

    'gps' => [
        'title' => 'GPS Tracker',
        'subtitle' => 'ระบบติดตามรถ',
        'icon' => 'fas fa-solid fa-van-shuttle',
        'color' => 'bg-warning',
        'href' => 'https://gps10.root.sx/gps_tracker/login.php',
        'target' => '_blank',
        'permission' => 'gps.view',
        'items' => []
    ]
];

if ($current_role === 'admin') {
    $menu_structure['user-management'] = [
        'title'    => 'บัญชีผู้ใช้',
        'subtitle' => 'จัดการผู้ใช้และธุรกิจการ',
        'icon'     => 'fas fa-user',
        'color'    => 'bg-secondary',
        'items'    => [
            [
                'href'      => 'user_activity_log.php',
                'title'     => 'ประวัติการใช้งาน',
                'active_on' => ['user_activity_log.php']
            ],
            [
                'href'      => 'login_history.php',
                'title'     => 'ประวัติการเข้าสู่ระบบ',
                'active_on' => ['login_history.php']
            ],
            [
                'href'      => 'manage_user.php',
                'title'     => 'จัดการบัญชีผู้ใช้',
                'active_on' => ['manage_user.php']
            ],
            [
                'href'      => 'manage_permission.php',
                'title'     => 'กำหนดสิทธิ์ผู้ใช้งาน',
                'active_on' => ['manage_permission.php']
            ]
        ]
    ];
}
?>

<script>
    console.log('DEBUG_SIDEBAR', {
        role: '<?php echo $current_role; ?>',
        menus: '<?php echo implode(",", array_keys($menu_structure)); ?>'
    });
</script>
<div class="offcanvas-xl offcanvas-start bg-white d-flex flex-column h-100" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">

    <div class="sidebar-header d-flex flex-shrink-0 align-items-center px-3"
        style="height: 70px; background-color: var(--bs-primary); border-bottom: 1px solid rgba(255,255,255,0.1);">

        <a class="text-decoration-none d-flex align-items-center w-100 text-truncate gap-lg-3 gap-md-2 gap-2" href="dashboard.php">

            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                style="width: 40px; height: 40px; background-color: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255,255,255,0.2);">
                <i class="fas fa-fingerprint text-white" style="font-size: 1.2rem;"></i>
            </div>

            <div class="d-flex flex-column justify-content-center text-truncate">
                <span class="fw-bold text-white tracking-wide lh-1"
                    style="font-size: 1.25rem; letter-spacing: 1px;">
                    CSIMS
                </span>
                <span class="text-white text-opacity-75 text-truncate"
                    style="font-size: 0.75rem; margin-top: 3px; font-weight: 300;">
                    ระบบบริหารจัดการวัตถุพยาน
                </span>
            </div>
        </a>

        <button type="button" class="btn-close btn-close-white d-lg-none ms-auto"
            data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
    </div>

    <div class="flex-grow-1 overflow-y-auto custom-scrollbar p-3 shadow-sm">
        <div class="accordion accordion-flush" id="sidebarAccordion">
            <?php foreach ($menu_structure as $key => $group):
                $hasItems = !empty($group['items']);

                // เช็คสิทธิ์ระดับ group (สำหรับ direct link ที่ไม่มี sub-items)
                if (!$hasItems && isset($group['permission']) && !hasPermission($pdo, $group['permission'])) {
                    continue;
                }

                // เช็คว่ามี item ที่แสดงได้อย่างน้อย 1 อัน (ตาม permission)
                if ($hasItems) {
                    $visibleCount = 0;
                    foreach ($group['items'] as $item) {
                        $itemFile = basename($item['href']);
                        if (!isset($pagePermissionMap[$itemFile]) || hasPermission($pdo, $pagePermissionMap[$itemFile])) {
                            $visibleCount++;
                        }
                    }
                    if ($visibleCount === 0) continue; // ซ่อนกลุ่มถ้าไม่มี item ที่แสดงได้
                }

                $isActiveGroup = false;
                if ($hasItems) {
                    foreach ($group['items'] as $item) {
                        $childActivePages = isset($item['active_on']) ? $item['active_on'] : [basename($item['href'])];
                        if (in_array($current_page, $childActivePages)) {
                            $isActiveGroup = true;
                            break;
                        }
                    }
                } else {
                    $selfActivePages = isset($group['active_on']) ? $group['active_on'] : [basename($group['href'])];

                    if (in_array($current_page, $selfActivePages)) {
                        $isActiveGroup = true;
                    }
                }
            ?>
                <div class="accordion-item border-0 mb-2">
                    <h2 class="accordion-header" id="heading-<?php echo $key; ?>">
                        <button class="accordion-button sidebar-menu-item <?php echo $isActiveGroup ? '' : 'collapsed'; ?> 
                        <?php echo $isActiveGroup ? 'active-group-highlight' : 'text-secondary'; ?>
                            fw-semibold py-2 px-3 rounded-3 shadow-none <?php echo $hasItems ? '' : 'no-arrow'; ?>" type="button"
                            <?php if ($hasItems): ?>
                            data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $key; ?>"
                            aria-expanded="<?php echo $isActiveGroup ? 'true' : 'false'; ?>"
                            aria-controls="collapse-<?php echo $key; ?>"
                            <?php else: ?>
                            onclick="window.open('<?php echo $group['href'] ?? '#'; ?>','<?php echo $group['target'] ?? '_self'; ?>');"
                            <?php endif; ?>>

                            <div class="d-flex align-items-center w-100">
                                <div class="icon-circle <?php echo $group['color']; ?> text-white me-3 d-flex align-items-center justify-content-center rounded-circle shadow-sm"
                                    style="width: 32px; height: 32px; flex-shrink: 0;">
                                    <i class="<?php echo $group['icon']; ?> fa-sm"></i>
                                </div>

                                <div class="d-flex flex-column justify-content-center flex-grow-1" style="min-width: 0;">
                                    <div class="lh-base" style="font-size: 0.9rem;">
                                        <?php echo $group['title']; ?>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </h2>

                    <?php if ($hasItems): ?>
                        <div id="collapse-<?php echo $key; ?>" class="accordion-collapse collapse <?php echo $isActiveGroup ? 'show' : ''; ?>"
                            aria-labelledby="heading-<?php echo $key; ?>" data-bs-parent="#sidebarAccordion">

                            <div class="accordion-body p-0 pt-1 ps-3">
                                <ul class="list-unstyled mb-0 border-start border-2 border-opacity-25 ms-3 ps-3">
                                    <?php foreach ($group['items'] as $item):
                                        // เช็คสิทธิ์: ถ้าไฟล์นี้ต้องมี permission แต่ user ไม่มี → ข้ามไม่แสดง
                                        $itemFile = basename($item['href']);
                                        if (isset($pagePermissionMap[$itemFile]) && !hasPermission($pdo, $pagePermissionMap[$itemFile])) {
                                            continue;
                                        }

                                        $activePages = isset($item['active_on']) ? $item['active_on'] : [basename($item['href'])];
                                        $isActiveItem = in_array($current_page, $activePages);
                                        // เช็คสถานะ Disabled (ถ้าไม่ได้ตั้งไว้ใน Array ให้มีค่าเริ่มต้นเป็น false)
                                        $isDisabled = isset($item['disabled']) && $item['disabled'] === true;
                                    ?>
                                        <li class="position-relative">
                                            <a href="<?php echo $isDisabled ? '#' : $item['href']; ?>"
                                                class="d-flex align-items-center text-decoration-none py-2 px-2 w-100 rounded-2 mb-1 submenu-link 
                                                <?php echo $isActiveItem ? 'active' : ''; ?>
                                                <?php echo $isDisabled ? 'text-muted opacity-50 user-select-none' : ''; ?>"
                                                style="margin-left: -1px; <?php echo $isDisabled ? 'cursor: not-allowed; pointer-events: none;' : ''; ?>">

                                                <span class="d-inline-block rounded-circle me-2 submenu-bullet flex-shrink-0 
                                                    <?php echo $isDisabled ? 'bg-secondary' : ''; ?>">
                                                </span>

                                                <span class="lh-sm d-flex align-items-center justify-content-between w-100" style="font-size: 0.9rem;">
                                                    <?php echo $item['title']; ?>

                                                    <?php if ($isDisabled): ?>
                                                        <i class="far fa-clock small text-black-50 ms-1" title="กำลังพัฒนา"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>

                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="p-3 border-top mt-auto shadow-sm flex-shrink-0">
        <a href="logout.php" class="btn btn-transparent text-secondary w-100 d-flex align-items-center justify-content-start hover-bg-danger-light px-3 py-2 border-0">

            <i class="fas fa-sign-out-alt fa-lg me-3 d-flex align-items-center justify-content-center "
                style="width: 32px; height: 32px;"></i>

            <span class="fw-semibold" style="font-size: 0.9rem;">ออกจากระบบ</span>
        </a>
    </div>
</div>