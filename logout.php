<?php
// logout.php
require_once __DIR__ . '/includes/session_boot.php';

if (isset($_SESSION['user_id'])) {
    require_once 'db_config.php';
    require_once 'includes/activity_logger.php';
    recordFinalActivity($pdo, $_SESSION['user_id']);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    $path = function_exists('csims_session_cookie_path') ? csims_session_cookie_path() : ($params['path'] ?? '/');
    $secure = !empty($params['secure']);
    $clear = [
        'expires'  => time() - 42000,
        'path'     => $path,
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie(session_name(), '', $clear);
    setcookie(CSIMS_KEEP_COOKIE, '', $clear);
    $clear['path'] = '/';
    setcookie(session_name(), '', $clear);
    setcookie(CSIMS_KEEP_COOKIE, '', $clear);
}
session_unset();
session_destroy();

header('Location: login.html');
exit;
