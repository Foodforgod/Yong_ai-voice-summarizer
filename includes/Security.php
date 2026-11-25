<?php
declare(strict_types=1);

class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        self::startSession();
        if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals((string)$_SESSION['csrf_token'], $token);
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function sanitizeFilename(string $filename): string
    {
        $base = basename($filename);
        $clean = preg_replace('/[^\w.\- ()]/u', '_', $base) ?? 'recording';
        $clean = trim($clean, ". \t\n\r\0\x0B");
        if ($clean === '') {
            $clean = 'recording';
        }
        return mb_substr($clean, 0, 180);
    }

    public static function generateTempFilename(string $extension): string
    {
        $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $extension) ?? 'bin');
        if ($ext === '') {
            $ext = 'bin';
        }
        return 'rec_' . bin2hex(random_bytes(16)) . '.' . $ext;
    }

    /**
     * @param array<string, mixed> $file
     * @param array<int, string> $allowedExtensions
     * @param array<int, string> $allowedMimeTypes
     * @return array{valid: bool, error: string, extension: string, mime_type: string}
     */
    public static function validateUpload(
        array $file,
        int $maxBytes,
        array $allowedExtensions,
        array $allowedMimeTypes
    ): array {
        $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($errorCode !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE   => 'Uploaded file exceeds server upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE  => 'Uploaded file exceeds maximum allowed size (250MB).',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No recording file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload folder on server.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write uploaded file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
            ];
            return [
                'valid'     => false,
                'error'     => $messages[$errorCode] ?? 'Upload error occurred.',
                'extension' => '',
                'mime_type' => '',
            ];
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $origName = (string)($file['name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return [
                'valid'     => false,
                'error'     => 'Invalid uploaded file reference.',
                'extension' => '',
                'mime_type' => '',
            ];
        }

        if ($size <= 0) {
            return [
                'valid'     => false,
                'error'     => 'Uploaded file is empty.',
                'extension' => '',
                'mime_type' => '',
            ];
        }

        if ($size > $maxBytes) {
            return [
                'valid'     => false,
                'error'     => 'File size exceeds the 250MB limit.',
                'extension' => '',
                'mime_type' => '',
            ];
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            return [
                'valid'     => false,
                'error'     => 'Unsupported file extension. Allowed: MP3, WAV, M4A, AAC, FLAC, OGG, WMA, MP4, WMV.',
                'extension' => $ext,
                'mime_type' => '',
            ];
        }

        $detectedMime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $m = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                if (is_string($m)) {
                    $detectedMime = strtolower(trim($m));
                }
            }
        }

        if ($detectedMime !== '' && !in_array($detectedMime, $allowedMimeTypes, true)) {
            return [
                'valid'     => false,
                'error'     => 'Invalid or mismatched MIME type detected (' . self::escape($detectedMime) . ').',
                'extension' => $ext,
                'mime_type' => $detectedMime,
            ];
        }

        return [
            'valid'     => true,
            'error'     => '',
            'extension' => $ext,
            'mime_type' => $detectedMime !== '' ? $detectedMime : 'application/octet-stream',
        ];
    }

    public static function safeJoinPath(string $baseDir, string $filename): ?string
    {
        $cleanName = basename($filename);
        if ($cleanName !== $filename || $cleanName === '' || $cleanName === '.' || $cleanName === '..') {
            return null;
        }
        $realBase = realpath($baseDir);
        if ($realBase === false) {
            return null;
        }
        $candidate = $realBase . DIRECTORY_SEPARATOR . $cleanName;
        return $candidate;
    }

    public static function logError(string $logsDir, string $message): void
    {
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0775, true);
        }
        $line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $message . PHP_EOL;
        @file_put_contents($logsDir . DIRECTORY_SEPARATOR . 'app.log', $line, FILE_APPEND | LOCK_EX);
    }
}