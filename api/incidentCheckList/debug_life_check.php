<?php
/**
 * Debug: ตรวจสอบข้อมูล Life Checklist ที่บันทึกอยู่ใน DB
 * ใช้เพื่อวิเคราะห์ว่าข้อมูลถูกบันทึกครบหรือไม่
 * 
 * Usage: /csims/api/incidentCheckList/debug_life_check.php?incident_id=XXX
 */

require_once '../../db_config.php';
header('Content-Type: text/html; charset=utf-8');

$incidentId = intval($_GET['incident_id'] ?? 0);

echo "<h2>🔍 Debug Life Checklist - incident_id: {$incidentId}</h2>";

if ($incidentId <= 0) {
    echo "<p style='color:red;'>กรุณาระบุ ?incident_id=XXX</p>";
    // แสดงรายการล่าสุดให้เลือก
    $recent = $pdo->query("SELECT t.incident_id, t.create_date, t.edit_date, t.count_edit, 
                           LENGTH(t.incident_checklist_data) as json_length
                           FROM incident_checklist_transaction t 
                           ORDER BY COALESCE(t.edit_date, t.create_date) DESC LIMIT 20");
    echo "<h3>รายการล่าสุด 20 รายการ:</h3><table border='1' cellpadding='5'>";
    echo "<tr><th>incident_id</th><th>create_date</th><th>edit_date</th><th>count_edit</th><th>JSON Length</th><th>Link</th></tr>";
    while ($row = $recent->fetch()) {
        $link = "?incident_id={$row['incident_id']}";
        echo "<tr><td>{$row['incident_id']}</td><td>{$row['create_date']}</td><td>{$row['edit_date']}</td><td>{$row['count_edit']}</td><td>{$row['json_length']}</td><td><a href='{$link}'>ดู</a></td></tr>";
    }
    echo "</table>";
    exit;
}

// ============ 1. ตรวจสอบ Column Type ============
echo "<h3>1. ตรวจสอบ Column Type ของ incident_checklist_data</h3>";
$colInfo = $pdo->query("SHOW COLUMNS FROM incident_checklist_transaction LIKE 'incident_checklist_data'")->fetch();
echo "<pre>" . print_r($colInfo, true) . "</pre>";

$colType = strtoupper($colInfo['Type'] ?? '');
if (strpos($colType, 'LONGTEXT') !== false) {
    echo "<p style='color:green;'>✅ Column type: LONGTEXT (4GB max) - OK</p>";
} elseif (strpos($colType, 'MEDIUMTEXT') !== false) {
    echo "<p style='color:green;'>✅ Column type: MEDIUMTEXT (16MB max) - OK</p>";
} elseif (strpos($colType, 'TEXT') !== false && strpos($colType, 'MEDIUM') === false && strpos($colType, 'LONG') === false) {
    echo "<p style='color:red;'>⚠️ Column type: TEXT (65,535 bytes max) - อาจทำให้ JSON ถูก TRUNCATE ได้!</p>";
} else {
    echo "<p style='color:orange;'>Column type: {$colType}</p>";
}

// ============ 2. ดึงข้อมูลจาก DB ============
echo "<h3>2. ข้อมูลใน DB สำหรับ incident_id = {$incidentId}</h3>";
$stmt = $pdo->prepare("SELECT *, LENGTH(incident_checklist_data) as json_byte_length FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
$stmt->execute([$incidentId]);
$record = $stmt->fetch();

if (!$record) {
    echo "<p style='color:red;'>❌ ไม่พบข้อมูลสำหรับ incident_id = {$incidentId}</p>";
    exit;
}

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>id</th><td>{$record['id']}</td></tr>";
echo "<tr><th>incident_id</th><td>{$record['incident_id']}</td></tr>";
echo "<tr><th>create_by</th><td>{$record['create_by']}</td></tr>";
echo "<tr><th>create_date</th><td>{$record['create_date']}</td></tr>";
echo "<tr><th>edit_by</th><td>" . ($record['edit_by'] ?? 'NULL') . "</td></tr>";
echo "<tr><th>edit_date</th><td>" . ($record['edit_date'] ?? 'NULL') . "</td></tr>";
echo "<tr><th>count_edit</th><td>" . ($record['count_edit'] ?? '0') . "</td></tr>";
echo "<tr><th>JSON byte length</th><td>{$record['json_byte_length']} bytes</td></tr>";
echo "</table>";

// ============ 3. ตรวจสอบ JSON ============
echo "<h3>3. ตรวจสอบ JSON</h3>";
$jsonRaw = $record['incident_checklist_data'];

if (empty($jsonRaw)) {
    echo "<p style='color:red;'>❌ incident_checklist_data เป็นค่าว่าง!</p>";
    exit;
}

$data = json_decode($jsonRaw, true);
if ($data === null) {
    echo "<p style='color:red;'>❌ json_decode FAILED! Error: " . json_last_error_msg() . "</p>";
    echo "<p>JSON ถูก truncate หรือเสียหาย</p>";
    echo "<h4>ตัวอย่าง JSON (500 ตัวอักษรแรก):</h4>";
    echo "<pre>" . htmlspecialchars(substr($jsonRaw, 0, 500)) . "</pre>";
    echo "<h4>ตัวอย่าง JSON (500 ตัวอักษรสุดท้าย):</h4>";
    echo "<pre>" . htmlspecialchars(substr($jsonRaw, -500)) . "</pre>";
    exit;
}

echo "<p style='color:green;'>✅ JSON decode สำเร็จ</p>";

// ============ 4. ตรวจสอบข้อมูลแต่ละ section ============
echo "<h3>4. ตรวจสอบข้อมูลแต่ละ section</h3>";

$sections = [
    'general_info' => 'ข้อมูลทั่วไป',
    'scene_characteristics' => 'ลักษณะสถานที่เกิดเหตุ',
    'inspection_result' => 'ผลการตรวจสถานที่',
    'inspectors' => 'ผู้ตรวจสถานที่',
    'evidences' => 'วัตถุพยาน',
    'evidence_meta' => 'ข้อมูลวัตถุพยาน',
    'measurements' => 'บันทึกการตรวจเก็บ',
    'measurement_meta' => 'ข้อมูลการตรวจเก็บ',
    'handover' => 'การส่งมอบคืนสถานที่',
    'victim_info' => 'ข้อมูลผู้ประสบเหตุ',
    'body_info' => 'ข้อมูลร่างกาย',
    'photo_records' => 'บันทึกภาพถ่าย',
    'signatures' => 'ลายเซ็น',
    'photos' => 'รูปภาพ',
    'sketch_info' => 'แผนผังสังเขป',
    'body_diagram_info' => 'แผนผังร่างกาย',
    'body_diagram_strokes' => 'ข้อมูลเส้นร่างกาย'
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Section</th><th>ชื่อ</th><th>สถานะ</th><th>ข้อมูล</th></tr>";

foreach ($sections as $key => $label) {
    $exists = isset($data[$key]);
    $value = $data[$key] ?? null;
    $status = '❌ ไม่มี';
    $detail = '-';

    if ($exists) {
        if (is_array($value)) {
            $count = count($value);
            $isEmpty = empty(array_filter($value, function($v) {
                if (is_array($v)) return !empty(array_filter($v));
                return $v !== '' && $v !== null;
            }));
            if ($count === 0 || $isEmpty) {
                $status = '⚠️ ว่าง';
                $detail = "array({$count})";
            } else {
                $status = '✅ มีข้อมูล';
                $detail = "array({$count})";
            }
        } elseif (is_string($value)) {
            if (empty(trim($value))) {
                $status = '⚠️ ว่าง';
            } else {
                $status = '✅ มีข้อมูล';
                $detail = mb_substr($value, 0, 50);
            }
        }
    }

    echo "<tr><td>{$key}</td><td>{$label}</td><td>{$status}</td><td><small>" . htmlspecialchars($detail) . "</small></td></tr>";
}
echo "</table>";

// ============ 5. แสดงรายละเอียด scene_characteristics ============
echo "<h3>5. รายละเอียด scene_characteristics (ส่วนที่หาย)</h3>";
$sc = $data['scene_characteristics'] ?? [];
if (empty($sc)) {
    echo "<p style='color:red;'>❌ ไม่มีข้อมูล scene_characteristics เลย!</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    foreach ($sc as $k => $v) {
        $display = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : htmlspecialchars($v);
        $color = (empty($v) || $v === '' || (is_array($v) && empty($v))) ? '#fdd' : '#dfd';
        echo "<tr style='background:{$color}'><td>{$k}</td><td>{$display}</td></tr>";
    }
    echo "</table>";
}

// ============ 6. แสดงรายละเอียด general_info ============
echo "<h3>6. รายละเอียด general_info</h3>";
$gi = $data['general_info'] ?? [];
if (empty($gi)) {
    echo "<p style='color:red;'>❌ ไม่มีข้อมูล general_info เลย!</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    foreach ($gi as $k => $v) {
        $display = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : htmlspecialchars($v);
        $color = (empty($v) || $v === '' || (is_array($v) && empty($v))) ? '#fdd' : '#dfd';
        echo "<tr style='background:{$color}'><td>{$k}</td><td>{$display}</td></tr>";
    }
    echo "</table>";
}

// ============ 7. แสดง Full JSON (collapsible) ============
echo "<h3>7. Full JSON Data</h3>";
echo "<details><summary>คลิกเพื่อดู JSON ทั้งหมด ({$record['json_byte_length']} bytes)</summary>";
echo "<pre style='max-height:600px; overflow:auto; background:#f5f5f5; padding:10px;'>";
echo htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "</pre></details>";

echo "<hr><p><small>Generated: " . date('Y-m-d H:i:s') . "</small></p>";
