<?php
/**
 * ตรวจสอบและแก้ไข column type ของ incident_checklist_data
 * ถ้าเป็น TEXT (65KB) → เปลี่ยนเป็น LONGTEXT (4GB)
 * เพื่อป้องกัน JSON ถูกตัดทำให้รูปภาพหาย
 * 
 * วิธีใช้: เปิดผ่าน browser http://localhost/csims/fix_column_type.php
 */
require_once 'db_config.php';
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔧 ตรวจสอบ Column Type - incident_checklist_transaction</h2>";

// 1. ดู column type ปัจจุบัน
$stmt = $pdo->query("SHOW COLUMNS FROM incident_checklist_transaction WHERE Field = 'incident_checklist_data'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<p><b>Column:</b> incident_checklist_data</p>";
echo "<p><b>Type ปัจจุบัน:</b> " . htmlspecialchars($col['Type']) . "</p>";

$currentType = strtolower($col['Type']);

if ($currentType === 'text') {
    echo "<p style='color:red;'>⚠️ Column เป็น TEXT (จำกัด ~65KB) → JSON อาจถูกตัดทำให้รูปภาพหาย!</p>";
    echo "<p>กำลังเปลี่ยนเป็น LONGTEXT...</p>";
    
    $pdo->exec("ALTER TABLE incident_checklist_transaction MODIFY COLUMN incident_checklist_data LONGTEXT");
    echo "<p style='color:green;'>✅ เปลี่ยนเป็น LONGTEXT สำเร็จ!</p>";
    
} elseif ($currentType === 'mediumtext') {
    echo "<p style='color:orange;'>⚡ Column เป็น MEDIUMTEXT (จำกัด ~16MB) - ปลอดภัยพอสมควร</p>";
    echo "<p>แนะนำเปลี่ยนเป็น LONGTEXT เพื่อความปลอดภัย...</p>";
    
    $pdo->exec("ALTER TABLE incident_checklist_transaction MODIFY COLUMN incident_checklist_data LONGTEXT");
    echo "<p style='color:green;'>✅ เปลี่ยนเป็น LONGTEXT สำเร็จ!</p>";
    
} elseif ($currentType === 'longtext') {
    echo "<p style='color:green;'>✅ Column เป็น LONGTEXT อยู่แล้ว (จำกัด ~4GB) - ปลอดภัย</p>";
    
} else {
    echo "<p style='color:orange;'>ℹ️ Type ปัจจุบัน: {$currentType} - กำลังเปลี่ยนเป็น LONGTEXT...</p>";
    $pdo->exec("ALTER TABLE incident_checklist_transaction MODIFY COLUMN incident_checklist_data LONGTEXT");
    echo "<p style='color:green;'>✅ เปลี่ยนเป็น LONGTEXT สำเร็จ!</p>";
}

// 2. ตรวจ incident_report_data ด้วย
$stmt2 = $pdo->query("SHOW COLUMNS FROM incident_checklist_transaction WHERE Field = 'incident_report_data'");
$col2 = $stmt2->fetch(PDO::FETCH_ASSOC);
if ($col2) {
    $type2 = strtolower($col2['Type']);
    echo "<hr><p><b>Column:</b> incident_report_data</p>";
    echo "<p><b>Type ปัจจุบัน:</b> " . htmlspecialchars($col2['Type']) . "</p>";
    if ($type2 !== 'longtext') {
        $pdo->exec("ALTER TABLE incident_checklist_transaction MODIFY COLUMN incident_report_data LONGTEXT");
        echo "<p style='color:green;'>✅ เปลี่ยนเป็น LONGTEXT สำเร็จ!</p>";
    } else {
        echo "<p style='color:green;'>✅ เป็น LONGTEXT อยู่แล้ว</p>";
    }
}

// 3. ตรวจ JSON integrity ของข้อมูลที่มีอยู่
echo "<hr><h3>📊 ตรวจสอบ JSON Integrity</h3>";
$stmt3 = $pdo->query("SELECT id, incident_id, LENGTH(incident_checklist_data) as json_len, incident_checklist_data FROM incident_checklist_transaction ORDER BY id");
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Incident ID</th><th>JSON Size</th><th>JSON Valid</th><th>Photos Count</th><th>Signatures</th></tr>";
while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    $decoded = json_decode($row['incident_checklist_data'], true);
    $isValid = $decoded !== null ? '✅ Valid' : '❌ INVALID';
    $validColor = $decoded !== null ? 'green' : 'red';
    $photoCount = ($decoded && isset($decoded['photos'])) ? count($decoded['photos']) : 0;
    $sigCount = ($decoded && isset($decoded['signatures'])) ? count($decoded['signatures']) : 0;
    $sizeKB = round($row['json_len'] / 1024, 1);
    
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['incident_id']}</td>";
    echo "<td>{$sizeKB} KB</td>";
    echo "<td style='color:{$validColor};'>{$isValid}</td>";
    echo "<td>{$photoCount}</td>";
    echo "<td>{$sigCount}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><p>🎉 เสร็จสิ้นการตรวจสอบ</p>";
