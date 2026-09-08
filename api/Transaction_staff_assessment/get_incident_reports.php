<?php
session_start();
require '../../db_config.php'; // ตรวจสอบ Path นี้ให้ดีว่าถูกต้อง
require __DIR__ . '/../../helpers/report_no.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ป้องกันการเข้าถึงโดยไม่ได้ Login (ตามตัวอย่างที่ใช้งานได้)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $search = $_GET['q'] ?? '';

    // 2. ปรับ Query ให้รองรับการค้นหาและกรองประเภทคดี
    // แนะนำให้ใส่ t1.statusDelete = 0 ด้วยถ้าใน DB มีฟิลด์นี้
    $sql = "SELECT receiveNoti_No, receiveNotiReportNo_TH, complaints_type 
            FROM rn_ReceiveNoti 
            WHERE complaints_type IN ('01', '02', '03') 
            AND statusDelete = 0
            AND (receiveNotiReportNo_TH LIKE ? OR receiveNoti_No LIKE ?)
            ORDER BY receiveNotiReportNo_TH ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$search%", "%$search%"]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($data as $row) {
        $results[] = [
            'id' => $row['receiveNotiReportNo_TH'], 
            'text' => $row['receiveNotiReportNo_TH'], 
            'complaints_type' => $row['complaints_type'], 
            'doc_no' => $row['receiveNoti_No']
        ];
    }

    // 3. ส่งกลับแบบ success พร้อมรองรับภาษาไทย
    echo json_encode([
        'status' => 'success',
        'results' => $results
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // 4. ถ้า SQL พัง จะส่ง Error 500 พร้อมข้อความแจ้งเตือนที่อ่านออก
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลเลขรายงาน: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>