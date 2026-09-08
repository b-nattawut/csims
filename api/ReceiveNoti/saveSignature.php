<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. รับค่าจาก POST
$ApproveID    = intval($_POST["approveID"]);
$ComplaintsID = intval($_POST["ComplaintsID"]);
$userId       = intval($_POST["userId"]);
$SignatureRaw = $_POST["signature"]; // ข้อมูล Base64 จากหน้าบ้าน
$seqSignature = intval($_POST["seqSignature"]);

try {
    // 2. จัดการไฟล์รูปภาพ (แปลง Base64 เป็น Binary)
    // ตัดส่วน data:image/png;base64, ออก
    $img = str_replace('data:image/png;base64,', '', $SignatureRaw);
    $img = str_replace(' ', '+', $img);
    $data = base64_decode($img);

    // 3. ตั้งชื่อไฟล์ (ใช้ ID + Timestamp เพื่อป้องกันชื่อซ้ำ)
    $fileName = "sig_" . $ApproveID . "_" . time() . ".png";
    
    // Path สำหรับการ Save ไฟล์ (นับจากไฟล์นี้ออกไปหา folder uploads)
    $uploadDir = "../../uploads/signatures/"; 
    
    // ตรวจสอบว่ามีโฟลเดอร์ไหม ถ้าไม่มีให้สร้าง (เผื่อไว้)
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileFullPath = $uploadDir . $fileName;

    // 4. บันทึกไฟล์ลงใน Directory
    if (file_put_contents($fileFullPath, $data)) {
        
        $sql = "UPDATE rn_ReceiveNotiApprove 
        SET signature = ?, signature_date = NOW()
        WHERE ApproveID = ? AND ComplaintsID = ? AND userId = ? AND seqSignature = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $fileName,   
            $ApproveID,
            $ComplaintsID,
            $userId,
            $seqSignature
        ]);

        echo json_encode([
            "status" => "success",
            "message" => "บันทึกไฟล์ $fileName เรียบร้อยแล้ว"
        ]);

    } else {
        throw new Exception("ไม่สามารถบันทึกไฟล์ลงใน Directory ได้ โปรดเช็ค Permission");
    }

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>