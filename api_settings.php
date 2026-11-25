<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/GeminiClient.php';

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

$action = trim((string)($input['action'] ?? ''));

$resolveModel = static function (string $selectVal, string $customVal, string $fallback): string {
    if ($selectVal === 'custom') {
        $c = trim($customVal);
        return $c !== '' ? $c : $fallback;
    }
    $s = trim($selectVal);
    return $s !== '' ? $s : $fallback;
};

if ($action === 'test_gemini') {
    $endpoint = rtrim(trim((string)($input['gemini_endpoint'] ?? $config['gemini']['endpoint'])), '/');
    $model = $resolveModel(
        (string)($input['gemini_model_select'] ?? ''),
        (string)($input['gemini_custom_model'] ?? ''),
        (string)$config['gemini']['model']
    );
    $apiKey = trim((string)($input['gemini_api_key'] ?? ''));
    if ($apiKey === '') {
        $apiKey = (string)$config['gemini']['api_key'];
    }

    if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid Gemini endpoint URL.']);
        exit;
    }

    $client = new GeminiClient($endpoint, $model, $apiKey);
    $res = $client->testConnection();
    echo json_encode([
        'ok'      => $res['ok'],
        'message' => $res['message'],
        'error'   => $res['ok'] ? '' : $res['message'],
    ]);
    exit;
}

if ($action === 'test_agnes') {
    $endpoint = rtrim(trim((string)($input['agnes_endpoint'] ?? $config['agnes']['endpoint'])), '/');
    $model = $resolveModel(
        (string)($input['agnes_model_select'] ?? ''),
        (string)($input['agnes_custom_model'] ?? ''),
        (string)$config['agnes']['model']
    );
    $apiKey = trim((string)($input['agnes_api_key'] ?? ''));
    if ($apiKey === '') {
        $apiKey = (string)$config['agnes']['api_key'];
    }

    if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid Agnes AI endpoint URL.']);
        exit;
    }
    if ($apiKey === '') {
        echo json_encode(['ok' => false, 'error' => 'Agnes AI API Key is not configured.']);
        exit;
    }

    $url = $endpoint . '/chat/completions';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            'model'    => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Reply with OK only.'],
            ],
            'max_tokens' => 16,
        ]),
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $err !== '') {
        echo json_encode(['ok' => false, 'error' => 'Agnes connection error: ' . $err]);
        exit;
    }

    if ($code < 200 || $code >= 300) {
        $decoded = json_decode((string)$raw, true);
        $msg = is_array($decoded) ? (string)($decoded['error']['message'] ?? $decoded['message'] ?? '') : '';
        echo json_encode([
            'ok'    => false,
            'error' => 'Agnes API error (HTTP ' . $code . ')' . ($msg !== '' ? ': ' . $msg : ''),
        ]);
        exit;
    }

    echo json_encode([
        'ok'      => true,
        'message' => 'Connected to Agnes AI (' . $model . ').',
    ]);
    exit;
}

if ($action === 'save_settings') {
    $geminiEndpoint = rtrim(trim((string)($input['gemini_endpoint'] ?? $config['gemini']['endpoint'])), '/');
    $geminiModel = $resolveModel(
        (string)($input['gemini_model_select'] ?? ''),
        (string)($input['gemini_custom_model'] ?? ''),
        (string)$config['gemini']['model']
    );
    $geminiKeyInput = trim((string)($input['gemini_api_key'] ?? ''));
    $geminiKey = $geminiKeyInput !== '' ? $geminiKeyInput : (string)$config['gemini']['api_key'];

    $agnesEndpoint = rtrim(trim((string)($input['agnes_endpoint'] ?? $config['agnes']['endpoint'])), '/');
    $agnesModel = $resolveModel(
        (string)($input['agnes_model_select'] ?? ''),
        (string)($input['agnes_custom_model'] ?? ''),
        (string)$config['agnes']['model']
    );
    $agnesKeyInput = trim((string)($input['agnes_api_key'] ?? ''));
    $agnesKey = $agnesKeyInput !== '' ? $agnesKeyInput : (string)$config['agnes']['api_key'];

    if (!filter_var($geminiEndpoint, FILTER_VALIDATE_URL) || !filter_var($agnesEndpoint, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Please provide valid API endpoint URLs.']);
        exit;
    }

    $escapeEnv = static function (string $val): string {
        $clean = str_replace(["\r", "\n"], '', $val);
        if ($clean === '' || preg_match('/[\s"#\']/', $clean)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $clean) . '"';
        }
        return $clean;
    };

    $appUrl = (string)$config['app_url'];
    if ($appUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $appUrl = $scheme . '://' . $host;
    }

    $envLines = [
        'APP_URL=' . $escapeEnv($appUrl),
        'GEMINI_API_KEY=' . $escapeEnv($geminiKey),
        'GEMINI_MODEL=' . $escapeEnv($geminiModel),
        'GEMINI_ENDPOINT=' . $escapeEnv($geminiEndpoint),
        'AGNES_API_KEY=' . $escapeEnv($agnesKey),
        'AGNES_MODEL=' . $escapeEnv($agnesModel),
        'AGNES_ENDPOINT=' . $escapeEnv($agnesEndpoint),
        '',
    ];

    $envPath = (string)$config['env_path'];
    $written = @file_put_contents($envPath, implode(PHP_EOL, $envLines), LOCK_EX);

    if ($written === false) {
        Security::logError((string)$config['logs_dir'], 'Failed writing .env at ' . $envPath);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Unable to write to .env file. Check file permissions.']);
        exit;
    }

    echo json_encode([
        'ok'      => true,
        'message' => 'Configuration saved to .env successfully.',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown settings action.']);