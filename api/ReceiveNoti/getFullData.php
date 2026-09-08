<?php
require '../../db_config.php';

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

// ============================
// LOAD .ENV (เพิ่มตรงนี้)
// =============================
function loadEnv($path)
{
    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// โหลด .env (สำคัญมาก)
loadEnv(__DIR__ . '/../../.env');


// =====================
// helper
// =====================
function jsonOut(bool $ok, string $msg, $data = null, int $code = 200): void
{
    http_response_code($code);
    $res = ['status' => $ok ? 'success' : 'error', 'message' => $msg];

    if ($data !== null) {
        $res['data'] = $data;
    }

    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}


// =====================
// METHOD CHECK
// =====================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonOut(false, 'Method Not Allowed', null, 405);
}


// =====================
// API KEY CHECK (.env)
// =====================
$headers = getallheaders();

$clientKey = $headers['x-api-key'] ?? $headers['X-API-KEY'] ?? '';
$serverKey = getenv('API_KEY');

if (!$serverKey) {
    jsonOut(false, 'API KEY not configured', null, 500);
}

if ($clientKey !== $serverKey) {
    jsonOut(false, 'Unauthorized', null, 401);
}


// =====================
// validate docno
// =====================
$docno = trim($_GET['docno'] ?? '');

if ($docno === '') {
    jsonOut(false, 'กรุณาระบุเลขที่เอกสาร (docno)', null, 400);
}

try {

    // =====================
    // 1) INCIDENT
    // =====================
    $stmtIncident = $pdo->prepare("
        SELECT
            r.id,
            r.receiveNoti_No,
            r.receiveNotiReportNo,
            r.location_create,
            r.create_date,
            r.complaints_type,
            r.complaints_type_other,
            r.complaints_From,
            r.complaints_From_Device,
            r.complaints_From_Device_Other,
            r.location_crime,
            r.basic_Info,
            r.time_Occurrence,
            r.inquiry_official_full_name,
            r.inquiry_official_phone,
            r.suffer_full_name,
            r.suffer_phone,
            r.provinceID,
            r.province,
            r.statusChecklist,
            r.create_by,
            r.edit_by,
            r.edit_date
        FROM rn_ReceiveNoti r
        WHERE r.receiveNoti_No = :docno
          AND r.statusDelete = 0
        LIMIT 1
    ");

    $stmtIncident->execute([':docno' => $docno]);
    $incident = $stmtIncident->fetch();

    if (!$incident) {
        jsonOut(false, 'ไม่พบข้อมูลเลขที่เอกสาร: ' . $docno, null, 404);
    }

    $incidentId = (int)$incident['id'];

    // =====================
    // 2) CHECKLIST
    // =====================
    $stmtChecklist = $pdo->prepare("
        SELECT *
        FROM incident_checklist_transaction
        WHERE incident_id = :iid
        LIMIT 1
    ");

    $stmtChecklist->execute([':iid' => $incidentId]);
    $checklist = $stmtChecklist->fetch();

    if ($checklist) {
        if (!empty($checklist['incident_checklist_data'])) {
            $tmp = json_decode($checklist['incident_checklist_data'], true);
            $checklist['incident_checklist_data'] = $tmp ?? $checklist['incident_checklist_data'];
        }

        if (!empty($checklist['incident_report_data'])) {
            $tmp = json_decode($checklist['incident_report_data'], true);
            $checklist['incident_report_data'] = $tmp ?? $checklist['incident_report_data'];
        }
    }

    // =====================
    // 3) FILES
    // =====================
    $stmtFiles = $pdo->prepare("
        SELECT id
        FROM incident_checklist_transaction_file
        WHERE id_incident = :iid
    ");

    $stmtFiles->execute([':iid' => $incidentId]);
    $fileRows = $stmtFiles->fetchAll();

    $files = [];

    foreach ($fileRows as $f) {
        $files[] = [
            'file_id'  => (int)$f['id'],
            'file_url' => '/csims/api/incidentCheckList/getFile.php?id=' . (int)$f['id']
        ];
    }

    // =====================
    // OUTPUT
    // =====================
    jsonOut(true, 'success', [
        'incident'  => $incident,
        'checklist' => $checklist ?: null,
        'files'     => $files
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonOut(false, 'เกิดข้อผิดพลาดในการดึงข้อมูล', null, 500);
}