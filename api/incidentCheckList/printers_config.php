<?php
/**
 * รายชื่อเครื่องพิมพ์ Zebra ที่อนุญาตให้ใช้งาน (Whitelist)
 * 
 * - key  = printer_id (ส่งจาก client)
 * - ip   = IP ของเครื่องพิมพ์บนเครือข่าย
 * - port = TCP port (default 9100 = RAW / ZPL over TCP)
 * - name = ชื่อที่จะแสดงใน dropdown ฝั่ง client
 * 
 * วิธีเพิ่มเครื่องใหม่: เพิ่ม entry ใน array นี้ที่เดียว
 */
$allowed_printers = [
    'printer_1' => [
        'ip'   => '192.168.1.50',
        'port' => 9100,
        'name' => 'Zebra เครื่อง 1',
    ],
    'printer_2' => [
        'ip'   => '172.20.10.15',
        'port' => 9100,
        'name' => 'Zebra เครื่อง 2',
    ],
    'printer_3' => [
        'ip'   => '172.20.10.16',
        'port' => 9100,
        'name' => 'Zebra เครื่อง 3',
    ],
    // เพิ่มเครื่องอื่น — ตั้ง IP static ให้ไม่ซ้ำกัน แล้วเพิ่ม entry ตรงนี้
];
