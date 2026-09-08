<?php
$display_name = $display_name ?? 'Guest';
$display_position = $display_position ?? '';
$first_char = mb_substr($logged_in_user['first_name'] ?? 'G', 0, 1);
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm fixed-top navbar-custom" style="min-height: 70px;">
    <div class="container-fluid position-relative px-3 px-lg-4">
        <div class="d-flex align-items-center">

            <button class="sidebar-toggle-btn d-none d-xl-flex border-0" type="button" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>

            <button class="sidebar-toggle-btn d-xl-none border-0 me-2 " type="button"
                data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                <i class="fas fa-bars"></i>
            </button>

            <div class="text-white opacity-75 fw-bold fs-5 text-truncate ms-2 d-none d-sm-block d-lg-none" style="max-width: 75vw;">
                <?php echo isset($title) ? explode(' - ', $title)[0] : 'ระบบวัตถุพยาน'; ?>
            </div>

            <div class="text-white opacity-75 fw-bold fs-5 text-truncate ms-2 d-block d-sm-none" style="max-width: 55vw;">
                <?php echo isset($title) ? explode(' - ', $title)[0] : 'ระบบวัตถุพยาน'; ?>
            </div>
        </div>

        <div class="position-absolute start-50 top-50 translate-middle text-center d-none d-lg-block"
            style="z-index: 1; width: auto;">

            <div class="text-white opacity-75 fw-bold fs-5 text-truncate"
                style="max-width: 40vw;">
                <?php echo isset($title) ? explode(' - ', $title)[0] : 'ระบบวัตถุพยาน'; ?>
            </div>
        </div>

        <div class="d-flex align-items-center ms-auto position-relative" style="z-index: 10;">

            <!-- <a href="#" class="text-white position-relative opacity-75 hover-opacity-100 transition-base">
                <i class="fas fa-bell fs-5"></i>
                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                    <span class="visually-hidden">New alerts</span>
                </span>
            </a> -->

            <div class="d-flex align-items-center gap-2">

                <div id="connection-status" class="d-flex align-items-center text-white-50 me-2" title="สถานะการเชื่อมต่อ" data-bs-toggle="tooltip" data-bs-placement="bottom" style="cursor: help;">
                    <i class="fas fa-circle-notch fa-spin"></i>
                </div>

                <button id="sync-status-btn" class="btn btn-warning btn-sm rounded-pill d-none shadow-sm fw-bold px-3 py-0" style="height: 30px;" onclick="triggerManualSync()">
                    <i class="fas fa-sync-alt fa-spin me-1" style="font-size: 0.8rem;"></i>
                    <span class="d-none d-sm-inline" style="font-size: 0.85rem;">รอส่ง</span>
                    <span class="badge bg-dark bg-opacity-25 ms-1 rounded-circle" id="queue-count" style="font-size: 0.75rem;">0</span>
                </button>
            </div>

            <div class="vr bg-white opacity-25 d-block align-self-center mx-1 mx-sm-2" style="height: 32px; width: 1px;"></div>

            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle no-caret user-profile-link py-1 px-1 px-lg-2 py-lg-2" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="text-end me-2 d-none d-lg-block line-height-sm">
                        <div class="fw-bold" style="font-size: 0.95rem;"><?php echo htmlspecialchars($display_name); ?></div>
                        <div class="opacity-75" style="font-size: 0.8rem;"><?php echo htmlspecialchars($display_position); ?></div>
                    </div>
                    <div class="avatar-circle bg-white text-primary fw-bold d-flex align-items-center justify-content-center rounded-circle shadow-sm" style="width: 40px; height: 40px; border: 2px solid rgba(255,255,255,0.2); font-size: 1.4rem;">
                        <span class="avatar-text"><?php echo $first_char; ?></span>
                    </div>
                </a>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="userDropdown" style="min-width: 240px;">
                    <li class="d-lg-none">
                        <div class="d-flex align-items-center ps-2 pe-3 py-3 border-bottom mb-2">
                            <div class="me-2">
                                <div class="avatar-circle bg-light text-primary fw-bold d-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px; font-size: 1.2rem;">
                                    <span class="avatar-text"><?php echo $first_char; ?></span>
                                </div>
                            </div>
                            <div class="flex-grow-1 text-start" style="min-width: 170px;">
                                <h6 class="mb-0 fw-bold text-dark lh-sm" style="font-size: 0.95rem; word-break: break-word;"><?php echo htmlspecialchars($display_name); ?></h6>
                                <small class="text-muted d-block text-truncate" style="font-size: 0.85rem; opacity: 0.75;"><?php echo htmlspecialchars($display_position); ?></small>
                            </div>
                        </div>
                    </li>
                    
                    <li><a class="dropdown-item py-2 px-3 rounded <?php echo ($current_page == 'user_profile.php') ? 'active' : ''; ?>" href="user_profile.php"><i class="fas fa-user-circle fa-fw me-2 opacity-75"></i> ข้อมูลส่วนตัว</a></li>
                    <li>
                        <hr class="dropdown-divider my-2 opacity-50">
                    </li>
                    <li><a class="dropdown-item py-2 px-3 rounded text-danger dropdown-item-danger" href="logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i> ออกจากระบบ</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>