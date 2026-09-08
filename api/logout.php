<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../db_config.php';
$p_user_id = $_SESSION['user_id'] ?? 0; 

if ($p_user_id > 0) { 
		try {
						
						$p_logtype_id = 1;
						$p_action = "User logout user_id: " . $p_user_id;
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

		}

		catch (Exception $e) {
			http_response_code(500);
			echo json_encode(["error" => $e->getMessage()]);
		}
}

			session_unset();
			session_destroy();
			
			echo json_encode(["message" => "Logout successful"]);
?>