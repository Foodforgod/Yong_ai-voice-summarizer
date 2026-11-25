<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Security.php';

Security::startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!Security::verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token. Please refresh and try again.']);
    exit;
}

if (!isset($_FILES['recording']) || !is_array($_FILES['recording'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No recording file received.']);
    exit;
}

$validation = Security::validateUpload(
    $_FILES['recording'],
    (int)$config['max_upload_size'],
    (array)$config['allowed_extensions'],
    (array)$config['allowed_mime_types']
);

if (!$validation['valid']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $validation['error']]);
    exit;
}

$originalName = Security::sanitizeFilename((string)($_FILES['recording']['name'] ?? 'recording'));
$tempFilename = Security::generateTempFilename($validation['extension']);
$tempPath = Security::safeJoinPath((string)$config['temp_dir'], $tempFilename);

if ($tempPath === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Invalid temporary storage path.']);
    exit;
}

if (!move_uploaded_file((string)$_FILES['recording']['tmp_name'], $tempPath)) {
    Security::logError((string)$config['logs_dir'], 'Failed moving uploaded file to ' . $tempPath);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to store temporary recording on server.']);
    exit;
}

$duration = trim((string)($_POST['duration'] ?? ''));
if ($duration === '') {
    $duration = 'Unknown';
}

$sizeBytes = (int)filesize($tempPath);

$_SESSION['pending_upload'] = [
    'temp_file'     => $tempFilename,
    'original_name' => $originalName,
    'size_bytes'    => $sizeBytes,
    'extension'     => $validation['extension'],
    'mime_type'     => $validation['mime_type'],
    'duration'      => $duration,
    'uploaded_at'   => time(),
];

echo json_encode([
    'ok' => true,
    'upload' => [
        'temp_file'     => $tempFilename,
        'original_name' => $originalName,
        'size_bytes'    => $sizeBytes,
        'extension'     => $validation['extension'],
        'mime_type'     => $validation['mime_type'],
        'duration'      => $duration,
    ],
]);