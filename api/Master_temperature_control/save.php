<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');

// Debug: บันทึกข้อมูลที่รับมา
file_put_contents('debug_post.txt', date('Y-m-d H:i:s') . "\n" . print_r($_POST, true) . "\n\n", FILE_APPEND);

// รับข้อมูลจาก POST
$id = !empty($_POST["id"]) ? $_POST["id"] : null;
$dpt_name = $_POST["dpt_name"] ?? '';
$thermometer_number = $_POST["thermometer_number"] ?? '';
$timeuse = !empty($_POST["timeuse"]) ? $_POST["timeuse"] : null;
$location_use = $_POST["location_use"] ?? '';
$menstruation = !empty($_POST["menstruation"]) ? $_POST["menstruation"] : null;
$responsive_person_1 = !empty($_POST["responsive_person_1"]) ? $_POST["responsive_person_1"] : null;
$responsive_person_2 = !empty($_POST["responsive_person_2"]) ? $_POST["responsive_person_2"] : null;
$measurement_uncertainly = !empty($_POST["measurement_uncertainly"]) ? $_POST["measurement_uncertainly"] : null;
$start_times = !empty($_POST["start_times"]) ? $_POST["start_times"] : null;
$end_times = !empty($_POST["end_times"]) ? $_POST["end_times"] : null;
$measured_value = !empty($_POST["measured_value"]) ? $_POST["measured_value"] : null;
$status_remark = $_POST["status_remark"] ?? 'normal';

// Debug: บันทึกค่าที่ได้
file_put_contents('debug_post.txt', "Parsed values:\n" . json_encode([
    'dpt_name' => $dpt_name,
    'thermometer_number' => $thermometer_number,
    'timeuse' => $timeuse,
    'location_use' => $location_use,
    'menstruation' => $menstruation,
    'measurement_uncertainly' => $measurement_uncertainly,
    'measured_value' => $measured_value
], JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND);

try {
    if (!empty($id)) {
        // Update
        $stmt = $pdo->prepare("
            UPDATE master_control_temp SET
                dpt_name = ?,
                thermometer_number = ?,
                timeuse = ?,
                location_use = ?,
                menstruation = ?,
                responsive_person_1 = ?,
                responsive_person_2 = ?,
                measurement_uncertainly = ?,
                start_times = ?,
                end_times = ?,
                measured_value = ?,
                status_remark = ?,
                edit_by = ?,
                edit_date = ?
            WHERE id = ? AND statusDelete = 0
        ");

        $stmt->execute([
            $dpt_name,
            $thermometer_number,
            $timeuse,
            $location_use,
            $menstruation,
            $responsive_person_1,
            $responsive_person_2,
            $measurement_uncertainly,
            $start_times,
            $end_times,
            $measured_value,
            $status_remark,
            $_SESSION['user_id'] ?? null,
            $dateNow,
            $id
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["status" => "success", "message" => "แก้ไขข้อมูลสำเร็จ"]);
        } else {
            echo json_encode(["status" => "error", "message" => "ไม่พบข้อมูลที่ต้องการแก้ไข"]);
        }
    } else {
        // Insert
        $stmt = $pdo->prepare("
            INSERT INTO master_control_temp (
                dpt_name,
                thermometer_number,
                timeuse,
                location_use,
                menstruation,
                responsive_person_1,
                responsive_person_2,
                measurement_uncertainly,
                start_times,
                end_times,
                measured_value,
                status_remark,
                create_by,
                create_date
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->execute([
            $dpt_name,
            $thermometer_number,
            $timeuse,
            $location_use,
            $menstruation,
            $responsive_person_1,
            $responsive_person_2,
            $measurement_uncertainly,
            $start_times,
            $end_times,
            $measured_value,
            $status_remark,
            $_SESSION['user_id'] ?? null,
            $dateNow
        ]);

        $lastId = $pdo->lastInsertId();
        echo json_encode([
            "status" => "success", 
            "message" => "บันทึกข้อมูลสำเร็จ",
            "id" => $lastId
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
