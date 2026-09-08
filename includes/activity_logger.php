<?php
/**
 * Log User Activity
 * บันทึกการใช้งาน page ของ user
 */

function logUserActivity($pdo, $user_id, $page_name, $url) {
    try {
        // ตรวจสอบว่ามีข้อมูล session ก่อนหน้านี้ไหม
        $current_page_key = 'current_page_' . $user_id;
        $page_start_time_key = 'page_start_time_' . $user_id;
        
        // ถ้ามี page ก่อนหน้า ให้คำนวณเวลาและบันทึก
        if (isset($_SESSION[$current_page_key]) && $_SESSION[$current_page_key] !== null) {
            $prev_page = $_SESSION[$current_page_key];
            $prev_url = $_SESSION[$current_page_key . '_url'] ?? '';
            $start_time = $_SESSION[$page_start_time_key] ?? time();
            
            $duration = time() - $start_time;
            
            // เก็บ log ของหน้าก่อนหน้า
            if ($duration > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO log_user_usage (user_id, url, name_menu, date_use_page, total_time_use)
                    VALUES (?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([$user_id, $prev_url, $prev_page, $duration]);
            }
        }
        
        // บันทึก page ปัจจุบัน
        $_SESSION[$current_page_key] = $page_name;
        $_SESSION[$current_page_key . '_url'] = $url;
        $_SESSION[$page_start_time_key] = time();
        
    } catch (PDOException $e) {
        // ไม่แสดง error แค่บันทึก log
        error_log("Activity logging error: " . $e->getMessage());
    }
}

/**
 * Record final activity on page exit
 * เรียกใช้เมื่อปิด page
 */
function recordFinalActivity($pdo, $user_id) {
    try {
        $current_page_key = 'current_page_' . $user_id;
        $page_start_time_key = 'page_start_time_' . $user_id;
        
        if (isset($_SESSION[$current_page_key]) && $_SESSION[$current_page_key] !== null) {
            $page_name = $_SESSION[$current_page_key];
            $url = $_SESSION[$current_page_key . '_url'] ?? '';
            $start_time = $_SESSION[$page_start_time_key] ?? time();
            
            $duration = time() - $start_time;
            
            if ($duration > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO log_user_usage (user_id, url, name_menu, date_use_page, total_time_use)
                    VALUES (?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([$user_id, $url, $page_name, $duration]);
            }
            
            // เคลียร์ session
            unset($_SESSION[$current_page_key]);
            unset($_SESSION[$page_start_time_key]);
        }
    } catch (PDOException $e) {
        error_log("Final activity recording error: " . $e->getMessage());
    }
}
?>
