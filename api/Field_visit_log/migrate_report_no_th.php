<?php
/**
 * สคริปต์ครั้งเดียว: เพิ่มคอลumn report_no_TH และเติมค่าย้อนหลัง
 * เปิดผ่านเบราว์เซอร์: /csims/api/Field_visit_log/migrate_report_no_th.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
require __DIR__ . '/../../helpers/report_no.php';

header("Content-Type: text/plain; charset=UTF-8");

try {
    try {
        $pdo->query("SELECT report_no_TH FROM field_visit_logs LIMIT 1");
        echo "[SKIP] มีคอลumn report_no_TH อยู่แล้ว\n";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE field_visit_logs ADD COLUMN report_no_TH VARCHAR(50) NULL AFTER report_no");
        echo "[OK] เพิ่มคอลumn report_no_TH เรียบร้อย\n";
    }

    $rows = $pdo->query(
        "SELECT id, report_no, report_no_TH FROM field_visit_logs WHERE status_delete = 0 AND report_no IS NOT NULL AND report_no <> ''"
    )->fetchAll(PDO::FETCH_ASSOC);

    $update = $pdo->prepare("UPDATE field_visit_logs SET report_no_TH = ? WHERE id = ?");
    $count = 0;
    foreach ($rows as $r) {
        $th = smartThaiReportOrDoc($r['report_no']);
        if ($th !== ($r['report_no_TH'] ?? '')) {
            $update->execute([$th, $r['id']]);
            $count++;
        }
    }

    echo "[OK] อัปเดตเลขที่รายงานภาษาไทย {$count} แถว\n";
    echo "เสร็จสิ้น\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "[ERROR] " . $e->getMessage() . "\n";
}
