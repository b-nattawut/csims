<?php
/**
 * API: Serve file (image) from incident_checklist_transaction_file as binary
 * Usage: getFile.php?id=123
 */

require_once '../../db_config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    exit('Invalid ID');
}

try {
    $stmt = $pdo->prepare("SELECT file_name FROM incident_checklist_transaction_file WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['file_name'])) {
        http_response_code(404);
        exit('File not found');
    }

    $blobData = $row['file_name'];

    // Detect MIME type from binary data
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($blobData);
    if (!$mime || $mime === 'application/octet-stream') {
        $mime = 'image/png';
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($blobData));
    header('Cache-Control: public, max-age=86400');
    header('Access-Control-Allow-Origin: *');
    echo $blobData;

} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error');
}
