<?php
declare(strict_types=1);

$rootPath = dirname(__DIR__);
$envFile  = $rootPath . DIRECTORY_SEPARATOR . '.env';

if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $parts = explode('=', $trimmed, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                if (
                    (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))
                ) {
                    $val = substr($val, 1, -1);
                }
                if ($key !== '' && getenv($key) === false) {
                    putenv($key . '=' . $val);
                    $_ENV[$key] = $val;
                }
            }
        }
    }
}

$envGet = static function (string $key, string $default = ''): string {
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return (string)$val;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string)$_ENV[$key];
    }
    return $default;
};
$storageDir =$rootPath . DIRECTORY_SEPARATOR . 'storage';
$tempDir    =$storageDir . DIRECTORY_SEPARATOR . 'temp';
$logsDir    =$storageDir . DIRECTORY_SEPARATOR . 'logs';
$uploadsDir =$rootPath . DIRECTORY_SEPARATOR . 'uploads';

foreach ([$storageDir, $tempDir,$logsDir, $uploadsDir] as$dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

return [
    'app_name'        => 'AI Voice Summarizer',
    'app_url'         => rtrim($envGet('APP_URL', ''), '/'),
    'root_path'       => $rootPath,
    'env_path'        => $envFile,
    'temp_dir'        => $tempDir,
    'logs_dir'        => $logsDir,
    'uploads_dir'     => $uploadsDir,
    'max_upload_size' => 250 * 1024 * 1024, // 250MB
    'inline_limit'    => 15 * 1024 * 1024,  // 15MB
    'allowed_extensions' => [
        'mp3', 'wav', 'm4a', 'aac', 'flac', 'ogg', 'wma',
        'mp4', 'wmv',
    ],
    'allowed_mime_types' => [
        'audio/mpeg', 'audio/mp3',
        'audio/wav', 'audio/x-wav', 'audio/wave',
        'audio/mp4', 'audio/x-m4a', 'audio/m4a',
        'audio/aac', 'audio/x-aac',
        'audio/flac', 'audio/x-flac',
        'audio/ogg', 'application/ogg',
        'audio/x-ms-wma', 'video/x-ms-wma',
        'video/mp4',
        'video/x-ms-wmv', 'video/wmv',
        'application/octet-stream',
    ],
    'gemini' => [
        'endpoint' => rtrim($envGet('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1'), '/'),
        'model'    => $envGet('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'api_key'  => $envGet('GEMINI_API_KEY', ''),
        'models'   => [
            'gemini-3.8-flash',
            'gemini-3.5-flash-lite',
        ],
    ],
    'agnes' => [
        'endpoint' => rtrim($envGet('AGNES_ENDPOINT', 'https://apihub.agnes-ai.com/v1'), '/'),
        'model'    => $envGet('AGNES_MODEL', 'agnes-3.0-flash'),
        'api_key'  => $envGet('AGNES_API_KEY', ''),
        'models'   => [
            'agnes-3.0-flash',
            'agnes-2.5-flash',
        ],
    ],
];