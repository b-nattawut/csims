<?php
/**
 * ตรวจสอบว่า role ปัจจุบันมีสิทธิ์ตาม permission_name หรือไม่
 * ใช้งาน: require_once 'includes/check_permission.php';
 *         if (hasPermission($pdo, 'incident.create')) { ... }
 *
 * @param PDO    $pdo             PDO connection
 * @param string $permission_name ชื่อสิทธิ์ เช่น 'incident.create'
 * @param string|null $role       role ที่จะเช็ค (default ใช้จาก session)
 * @return bool
 */
function hasPermission($pdo, $permission_name, $role = null) {
    if ($role === null) {
        $role = $_SESSION['role'] ?? '';
    }
    if (empty($role)) return false;

    static $cache = [];

    // ดึงสิทธิ์ทั้งหมดของ role นี้ครั้งเดียว แล้ว cache
    if (!isset($cache[$role])) {
        try {
            $stmt = $pdo->prepare("
                SELECT p.name 
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role_name = ?
            ");
            $stmt->execute([$role]);
            $cache[$role] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            $cache[$role] = [];
        }
    }

    return in_array($permission_name, $cache[$role]);
}
