<?php
session_start();
require '../db_config.php'; // ใช้ไฟล์เชื่อมต่อฐานข้อมูลแบบ PDO เช่น $pdo = new PDO(...);
header("Content-Type: application/json; charset=UTF-8");
$lang = $_SESSION['lang'] ?? 'en';
// อ่าน JSON จาก body

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);


if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
	if ($lang == 'th')
        $error = "ไม่ได้ส่งอีเมล์หรือรหัสผ่าน!";
      if ($lang == 'en')
        $error = "Missing email or password!";
    echo json_encode(["error" => $error]);
    exit;
}

try {
    // ตรวจสอบ user จากฐานข้อมูล
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($data['password'], $user['password'])) {
        
      // Login success
      $_SESSION['user_id'] = $user['user_id'];
      $_SESSION['role'] = $user['role'];
      $_SESSION['email'] = $user['email'];
     
		

        echo json_encode([
            "message" => "Login successful",
			"session" => "Session stored",
			"user_id" => $_SESSION['user_id'],
			"role" => $_SESSION['role'],
			"email" => $_SESSION['email']
        ]);
		
				$p_user_id = $_SESSION['user_id'];
				$p_logtype_id = 2;
				$p_action = "User authentication: " . $_SESSION['email'];
				$sql = "CALL sp_create_logs(:user_id, :logtype_id, :action)";
				
				// เตรียมคำสั่ง
				$stmt = $pdo->prepare($sql);


				// ผูกค่า (Bind parameters)
				// PDO::PARAM_INT สำหรับจำนวนเต็ม
				$stmt->bindParam(':user_id', $p_user_id, PDO::PARAM_INT); 
				 $stmt->bindParam(':logtype_id', $p_logtype_id, PDO::PARAM_INT); 
				// PDO::PARAM_STR สำหรับสตริง
				$stmt->bindParam(':action', $p_action, PDO::PARAM_STR); 
				
				// เรียกใช้ stored procedure
				$stmt->execute();
		
    } else {
        http_response_code(401);
	  if ($lang == 'th')
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
      if ($lang == 'en')
        $error = "Invalid username or password!";
        echo json_encode(["error" => $error]);
    }
   
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
