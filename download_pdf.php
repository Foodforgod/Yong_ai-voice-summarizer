<?php
declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Security.php';

Security::startSession();

$requested = (string)($_GET['file'] ?? '');
$cleanName = basename($requested);

if (
    $cleanName === '' ||
    $cleanName !== $requested ||
    !preg_match('/^report_[a-f0-9]{24}\.pdf$/', $cleanName)
) {
    http_response_code(400);
    echo 'Invalid report identifier.';
    exit;
}

$allowedReports = $_SESSION['pdf_reports'] ?? null;
if (!is_array($allowedReports) || !isset($allowedReports[$cleanName])) {
    http_response_code(403);
    echo 'Report access denied or session expired.';
    exit;
}

$fullPath = Security::safeJoinPath((string)$config['temp_dir'], $cleanName);
if ($fullPath === null || !is_file($fullPath) || !is_readable($fullPath)) {
    http_response_code(404);
    echo 'PDF report not found.';
    exit;
}

$downloadName = Security::sanitizeFilename((string)($allowedReports[$cleanName]['download_name'] ?? 'summary_report.pdf'));
if (!str_ends_with(strtolower($downloadName), '.pdf')) {
    $downloadName .= '.pdf';
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('Content-Length: ' . (string)filesize($fullPath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($fullPath);
exit;