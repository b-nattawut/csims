<?php

use Mpdf\Tag\P;

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$stmt = $pdo->prepare("SELECT t1.id,t1.receiveNoti_No,t1.location_create,DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i:%s') AS cvdate,t1.complaints_From,
CASE 
    WHEN t1.complaints_From_Device = 'b' THEN 'ทางหนังสือ'
    WHEN t1.complaints_From_Device = 't' THEN 'ทางโทรศัพท์' 
    WHEN t1.complaints_From_Device = 'r' THEN 'วิทยุสื่อสาร' 
    WHEN t1.complaints_From_Device = 'o' THEN 'อื่น ๆ'
    ELSE 'ไม่ระบุ' 
END AS complaintsdevice,
t1.location_crime,DATE_FORMAT(t1.time_Occurrence, '%d/%m/%Y %H:%i:%s') AS cvtimeoccurrence,
case when t1.complaints_type = '01' then 'คดีเกี่ยวกับทรัพย์'
when t1.complaints_type = '02' then 'คดีเกี่ยวกับชีวิต'
when t1.complaints_type = '03' then 'คดีเกี่ยวกับระเบิด'
when t1.complaints_type = '04' then 'คดีเพลิงไหม้'
when t1.complaints_type = '05' then 'คดีจราจร'
when t1.complaints_type = '06' then 'ตรวจเก็บวัตถุพยาน'
when t1.complaints_type = '07' then 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ'
ELSE 'ตรวจเก็บวัตถุพยานบุคคล' END AS complaintstype
,CONCAT(t3.first_name,' ',t3.last_name) AS fullname FROM rn_ReceiveNoti t1 
LEFT JOIN users t2 ON t1.create_by = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
WHERE t1.statusDelete = 0 AND t2.is_active = 1 
ORDER BY t1.id DESC
LIMIT 15 OFFSET 0;");

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$qryCountData = $pdo->prepare("SELECT count(id) as countdata from rn_ReceiveNoti WHERE statusDelete = 0");
$qryCountData->execute();
$countData = $qryCountData->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'data' => $data,
    'countData' => $countData
], JSON_UNESCAPED_UNICODE);

?>