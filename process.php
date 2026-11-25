<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/GeminiClient.php';
require_once __DIR__ . '/includes/PdfReport.php';

Security::startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$jsonBody = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
$input = is_array($jsonBody) ? $jsonBody : $_POST;

$csrfToken = is_string($input['csrf_token'] ?? null)
    ? $input['csrf_token']
    : (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

if (!Security::verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$tempFilename = basename(trim((string)($input['temp_file'] ?? '')));
$pending = $_SESSION['pending_upload'] ?? null;

if (
    $tempFilename === '' ||
    !is_array($pending) ||
    ($pending['temp_file'] ?? '') !== $tempFilename
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No matching uploaded recording found in session.']);
    exit;
}

$tempFilePath = Security::safeJoinPath((string)$config['temp_dir'], $tempFilename);
if ($tempFilePath === null || !is_file($tempFilePath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Temporary recording file not found.']);
    exit;
}

$allowedAudioLangs = ['Auto Detect', 'Chinese', 'English', 'Malay'];
$allowedSummaryLangs = ['Chinese', 'English', 'Malay'];

$audioLanguage = in_array((string)($input['audio_language'] ?? 'Auto Detect'), $allowedAudioLangs, true)
    ? (string)$input['audio_language']
    : 'Auto Detect';

$summaryLanguage = in_array((string)($input['summary_language'] ?? 'Chinese'), $allowedSummaryLangs, true)
    ? (string)$input['summary_language']
    : 'Chinese';

$clientDuration = trim((string)($input['duration'] ?? ''));
$duration = $clientDuration !== '' ? $clientDuration : (string)($pending['duration'] ?? 'Unknown');
$originalName = (string)($pending['original_name'] ?? 'recording');
$sizeBytes = (int)($pending['size_bytes'] ?? filesize($tempFilePath));
$extension = (string)($pending['extension'] ?? pathinfo($tempFilePath, PATHINFO_EXTENSION));
$detectedMime = (string)($pending['mime_type'] ?? '');

$gemini = new GeminiClient(
    (string)$config['gemini']['endpoint'],
    (string)$config['gemini']['model'],
    (string)$config['gemini']['api_key'],
    (int)$config['inline_limit']
);

$mediaMime = $gemini->resolveMimeType($extension, $detectedMime);

try {
    $result = $gemini->processMedia($tempFilePath, $mediaMime, $audioLanguage, $summaryLanguage);
} finally {
    // Always delete temporary uploaded recording after processing
    if (is_file($tempFilePath)) {
        @unlink($tempFilePath);
    }
    unset($_SESSION['pending_upload']);
}

if (!$result['ok']) {
    Security::logError((string)$config['logs_dir'], 'Gemini processing error: ' . $result['error']);
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

$generatedAt = gmdate('Y-m-d H:i:s') . ' UTC';
$sizeFormatted = number_format($sizeBytes / (1024 * 1024), 2) . ' MB';

$pdfFilename = 'report_' . bin2hex(random_bytes(12)) . '.pdf';
$pdfPath = Security::safeJoinPath((string)$config['temp_dir'], $pdfFilename);
$pdfReady = false;

if ($pdfPath !== null) {
    $pdfReady = PdfReport::generate(
        $pdfPath,
        [
            'filename'         => $originalName,
            'duration'         => $duration,
            'file_size'        => $sizeFormatted,
            'summary_language' => $summaryLanguage,
            'generated_at'     => $generatedAt,
        ],
        $result['summary']
    );
}

if (!$pdfReady) {
    Security::logError((string)$config['logs_dir'], 'Failed generating PDF report for ' . $originalName);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to generate PDF summary report.']);
    exit;
}

if (!isset($_SESSION['pdf_reports']) || !is_array($_SESSION['pdf_reports'])) {
    $_SESSION['pdf_reports'] = [];
}
$_SESSION['pdf_reports'][$pdfFilename] = [
    'created_at'    => time(),
    'download_name' => pathinfo($originalName, PATHINFO_FILENAME) . '_summary_report.pdf',
];

echo json_encode([
    'ok'         => true,
    'transcript' => $result['transcript'],
    'summary'    => $result['summary'],
    'meta'       => [
        'filename'         => $originalName,
        'duration'         => $duration,
        'file_size'        => $sizeFormatted,
        'audio_language'   => $audioLanguage,
        'summary_language' => $summaryLanguage,
        'generated_at'     => $generatedAt,
    ],
    'pdf_url'    => 'download_pdf.php?file=' . urlencode($pdfFilename),
]);