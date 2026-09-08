<?php
/**
 * ส่งรายชื่อเครื่องพิมพ์ Zebra ที่อนุญาต (เฉพาะ id + name)
 * ไม่เปิดเผย IP ให้ฝั่ง client
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/printers_config.php';

$list = [];
foreach ($allowed_printers as $id => $p) {
    $list[] = [
        'id'   => $id,
        'name' => $p['name'] ?? $id,
    ];
}

echo json_encode($list, JSON_UNESCAPED_UNICODE);
