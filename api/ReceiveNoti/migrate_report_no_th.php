<?php
/**
 * สคริปต์ครั้งเดียว: เพิ่มคอลัมน์ receiveNotiReportNo_TH และเติมค่าย้อนหลัง
 * วิธีใช้: เปิดผ่านเบราว์เซอร์บนเซิร์ฟเวอร์ เช่น
 *   http://<server>/api/ReceiveNoti/migrate_report_no_th.php
 * รันซ้ำได้ (idempotent) — จะไม่เพิ่มคอลัมน์ซ้ำ และเติมเฉพาะแถวที่ยังว่าง
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
require __DIR__ . '/../../helpers/report_no.php';

header("Content-Type: text/plain; charset=UTF-8");

try {
    // 1) เพิ่มคอลัมน์ ถ้ายังไม่มี
    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'rn_ReceiveNoti'
           AND COLUMN_NAME = 'receiveNotiReportNo_TH'"
    );
    $check->execute();
    $exists = (int)$check->fetchColumn() > 0;

    if (!$exists) {
        $pdo->exec(
            "ALTER TABLE rn_ReceiveNoti
             ADD COLUMN receiveNotiReportNo_TH VARCHAR(50) NULL AFTER receiveNotiReportNo"
        );
        echo "[OK] เพิ่มคอลัมน์ receiveNotiReportNo_TH เรียบร้อย\n";
    } else {
        echo "[SKIP] มีคอลัมน์ receiveNotiReportNo_TH อยู่แล้ว\n";
    }

    // 2) เติมค่าย้อนหลังเฉพาะแถวที่ยังว่าง
    $rows = $pdo->query(
        "SELECT id, receiveNotiReportNo
         FROM rn_ReceiveNoti
         WHERE receiveNotiReportNo IS NOT NULL
           AND receiveNotiReportNo <> ''
           AND (receiveNotiReportNo_TH IS NULL OR receiveNotiReportNo_TH = '')"
    )->fetchAll(PDO::FETCH_ASSOC);

    $update = $pdo->prepare(
        "UPDATE rn_ReceiveNoti SET receiveNotiReportNo_TH = ? WHERE id = ?"
    );

    $count = 0;
    foreach ($rows as $r) {
        $th = convertReportNoToThai($r['receiveNotiReportNo']);
        $update->execute([$th, $r['id']]);
        $count++;
    }

    echo "[OK] เติมค่าย้อนหลังเลขที่รายงาน {$count} แถว\n";

    // ============================================================
    // เลขที่เอกสาร (receiveNoti_No_TH)
    // ============================================================

    // 3) เพิ่มคอลัมน์ receiveNoti_No_TH ถ้ายังไม่มี
    $checkDoc = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'rn_ReceiveNoti'
           AND COLUMN_NAME = 'receiveNoti_No_TH'"
    );
    $checkDoc->execute();
    $docExists = (int)$checkDoc->fetchColumn() > 0;

    if (!$docExists) {
        $pdo->exec(
            "ALTER TABLE rn_ReceiveNoti
             ADD COLUMN receiveNoti_No_TH VARCHAR(50) NULL AFTER receiveNoti_No"
        );
        echo "[OK] เพิ่มคอลัมน์ receiveNoti_No_TH เรียบร้อย\n";
    } else {
        echo "[SKIP] มีคอลัมน์ receiveNoti_No_TH อยู่แล้ว\n";
    }

    // 4) เติมค่าย้อนหลังเลขที่เอกสารเฉพาะแถวที่ยังว่าง
    $docRows = $pdo->query(
        "SELECT id, receiveNoti_No
         FROM rn_ReceiveNoti
         WHERE receiveNoti_No IS NOT NULL
           AND receiveNoti_No <> ''
           AND (receiveNoti_No_TH IS NULL OR receiveNoti_No_TH = '')"
    )->fetchAll(PDO::FETCH_ASSOC);

    $updateDoc = $pdo->prepare(
        "UPDATE rn_ReceiveNoti SET receiveNoti_No_TH = ? WHERE id = ?"
    );

    $docCount = 0;
    foreach ($docRows as $r) {
        $th = convertDocNoToThai($r['receiveNoti_No']);
        $updateDoc->execute([$th, $r['id']]);
        $docCount++;
    }

    echo "[OK] เติมค่าย้อนหลังเลขที่เอกสาร {$docCount} แถว\n";

    // 5) แก้ค่าที่แปลงผิด (MD ต้องเป็น ช ไม่ใช่ ข) — re-convert จากค่าอังกฤษต้นทาง
    $repairRows = $pdo->query(
        "SELECT id, receiveNotiReportNo, receiveNotiReportNo_TH, receiveNoti_No, receiveNoti_No_TH
         FROM rn_ReceiveNoti
         WHERE (receiveNotiReportNo LIKE 'MD-%' OR receiveNoti_No REGEXP '-MD[0-9]+')"
    )->fetchAll(PDO::FETCH_ASSOC);

    $repairRpt = $pdo->prepare("UPDATE rn_ReceiveNoti SET receiveNotiReportNo_TH = ? WHERE id = ?");
    $repairDoc = $pdo->prepare("UPDATE rn_ReceiveNoti SET receiveNoti_No_TH = ? WHERE id = ?");
    $repairCount = 0;
    foreach ($repairRows as $r) {
        $changed = false;
        if (!empty($r['receiveNotiReportNo'])) {
            $thRpt = convertReportNoToThai($r['receiveNotiReportNo']);
            if ($thRpt !== ($r['receiveNotiReportNo_TH'] ?? '')) {
                $repairRpt->execute([$thRpt, $r['id']]);
                $changed = true;
            }
        }
        if (!empty($r['receiveNoti_No'])) {
            $thDoc = convertDocNoToThai($r['receiveNoti_No']);
            if ($thDoc !== ($r['receiveNoti_No_TH'] ?? '')) {
                $repairDoc->execute([$thDoc, $r['id']]);
                $changed = true;
            }
        }
        if ($changed) $repairCount++;
    }
    echo "[OK] แก้เลขคดีชีวิต (MD→ช) {$repairCount} แถว\n";
    echo "เสร็จสิ้น\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "[ERROR] " . $e->getMessage() . "\n";
}
