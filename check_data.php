<?php
require 'db_config.php';
// ดึงข้อมูล fire checklist ล่าสุด
$stmt = $pdo->query("SELECT ict.incident_id, ict.incident_checklist_data, rn.complaints_type 
    FROM incident_checklist_transaction ict 
    JOIN rn_ReceiveNoti rn ON ict.incident_id = rn.id 
    WHERE rn.complaints_type = '04' 
    ORDER BY ict.create_date DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo "NO FIRE DATA FOUND\n"; exit; }
$data = json_decode($row['incident_checklist_data'], true);
echo "incident_id: " . $row['incident_id'] . "\n";
echo "=== sketch_remark ===\n";
var_dump($data['sketch_remark'] ?? 'NOT_SET');
echo "\n=== evidence_action ===\n";
var_dump($data['damage']['evidence_action'] ?? 'NOT_SET');
echo "\n=== case_type ===\n";
echo $data['general_info']['case_type'] ?? 'N/A';
echo "\n=== Top-level keys ===\n";
print_r(array_keys($data));
echo "\n";
?>
