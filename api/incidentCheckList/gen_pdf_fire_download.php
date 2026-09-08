<?php
/**
 * gen_pdf_fire_download.php - Generate PDF file for Fire Case and force download
 * Uses wkhtmltopdf or browser print-to-PDF fallback
 */

require_once __DIR__ . '/../../db_config.php';

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    die("Error: Invalid Incident ID");
}

// Get report number for filename
$reportNo = 'fire_checklist';
try {
    $stmt = $pdo->prepare("SELECT incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $data = json_decode($row['incident_checklist_data'], true);
        if (!empty($data['general_info']['report_no'])) {
            $reportNo = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $data['general_info']['report_no']);
        }
    }
} catch (Exception $e) {}

// HTML URL to convert
$htmlUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
         . '://' . $_SERVER['HTTP_HOST'] 
         . '/csims/api/incidentCheckList/gen_pdf_fire_html.php?incident_id=' . $incident_id;

// Try wkhtmltopdf first
$wkhtmltopdfPaths = [
    'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
    'C:\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
    '/usr/local/bin/wkhtmltopdf',
    '/usr/bin/wkhtmltopdf',
    'wkhtmltopdf'
];

$wkhtmltopdf = null;
foreach ($wkhtmltopdfPaths as $path) {
    if (file_exists($path) || (stripos(PHP_OS, 'WIN') === false && shell_exec("which $path 2>/dev/null"))) {
        $wkhtmltopdf = $path;
        break;
    }
}

if ($wkhtmltopdf) {
    // Generate PDF using wkhtmltopdf
    $tmpFile = sys_get_temp_dir() . '/fire_pdf_' . $incident_id . '_' . time() . '.pdf';
    
    $cmd = '"' . $wkhtmltopdf . '" '
         . '--page-size A4 '
         . '--orientation Portrait '
         . '--margin-top 0 '
         . '--margin-bottom 0 '
         . '--margin-left 0 '
         . '--margin-right 0 '
         . '--encoding UTF-8 '
         . '--enable-local-file-access '
         . '--javascript-delay 1000 '
         . '"' . $htmlUrl . '" '
         . '"' . $tmpFile . '" 2>&1';
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($tmpFile)) {
        $filename = 'checklist_fire_' . $reportNo . '_' . date('Ymd') . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: private, max-age=0, must-revalidate');
        
        readfile($tmpFile);
        unlink($tmpFile);
        exit;
    }
}

// Fallback: redirect to HTML page with print instruction
header('Location: ' . $htmlUrl . '&download=1');
exit;
