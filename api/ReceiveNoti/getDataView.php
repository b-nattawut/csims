<?php

use Mpdf\Tag\P;

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$stmt = $pdo->prepare("SELECT t1.id,t1.receiveNoti_No,t1.location_create,DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i:%s') AS cvdate,t1.complaints_From,
case when t1.complaints_From_Device = 't' then 'ทางโทรศัพท์' ELSE 'วิทยุสื่อสาร' END AS complaintsdevice,
t1.location_crime,DATE_FORMAT(t1.time_Occurrence, '%d/%m/%Y %H:%i:%s') AS cvtimeoccurrence,
case when t1.complaints_type = '01' then 'คดีเกี่ยวกับทรัพย์'
when t1.complaints_type = '02' then 'คดีเกี่ยวกับชีวิต'
when t1.complaints_type = '03' then 'คดีเกี่ยวกับระเบิด'
when t1.complaints_type = '04' then 'คดีเพลิงไหม้'
when t1.complaints_type = '05' then 'คดีจราจร'
when t1.complaints_type = '06' then 'ตรวจเก็บวัตถุพยาน'
when t1.complaints_type = '07' then 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ'
ELSE 'ตรวจเก็บวัตถุพยานบุคคล' END AS complaintstype
,CONCAT(t3.first_name,' ',t3.last_name) AS fullname
,t1.location_crime
,t1.basic_Info
,t1.inquiry_official_full_name
,t1.inquiry_official_phone
,t1.suffer_full_name
,t1.suffer_phone FROM rn_ReceiveNoti t1 
LEFT JOIN users t2 ON t1.create_by = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
WHERE t1.statusDelete = 0 AND t2.is_active = 1");

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'data' => $data
], JSON_UNESCAPED_UNICODE);

?>