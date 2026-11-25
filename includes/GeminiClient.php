<?php
declare(strict_types=1);

class GeminiClient
{
    private string $endpoint;
    private string $model;
    private string $apiKey;
    private int $inlineLimit;

    public function __construct(string $endpoint, string $model, string$apiKey, int $inlineLimit = 15728640) {    $this->endpoint = rtrim(trim($endpoint), '/');
    $this->model = trim($model) !== '' ? trim($model) : 'gemini-3.5-flash-lite';
        $this->apiKey = trim($apiKey);
        $this->inlineLimit =$inlineLimit;
    }

    public function resolveMimeType(string $extension, string$detectedMime = ''): string
    {
        $map = [
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'm4a'  => 'audio/mp4',
            'aac'  => 'audio/aac',
            'flac' => 'audio/flac',
            'ogg'  => 'audio/ogg',
            'wma'  => 'audio/x-ms-wma',
            'mp4'  => 'video/mp4',
            'wmv'  => 'video/x-ms-wmv',
        ];

        $ext = strtolower(trim($extension));
        if (isset($map[$ext])) {
            return $map[$ext];
        }
        if ($detectedMime !== '' &&$detectedMime !== 'application/octet-stream') {
            return $detectedMime;
        }
        return 'audio/mpeg';
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if ($this->apiKey === '') {
            return ['ok' => false, 'message' => 'Gemini API Key is not configured.'];
        }

        $url = $this->endpoint . '/models/' . rawurlencode($this->model) . ':generateContent?key=' . urlencode($this->apiKey);$payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Reply with OK only.'],
                    ],
                ],
            ],
        ];

        $res =$this->curlJson($url, 'POST',$payload, ['Content-Type: application/json'], 30);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['error']];
        }

        $text = $this->extractTextFromResponse($res['data']);
        if ($text === '') {
            return ['ok' => false, 'message' => 'Connected, but received an empty response from Gemini.'];
        }

        return ['ok' => true, 'message' => 'Connected to ' . $this->model . ': ' . mb_substr(trim($text), 0, 80)];
    }

    /**
     * @return array{ok: bool, error: string, transcript: string, summary: array<string, mixed>}
     */
    public function processMedia(
        string $filePath,
        string $mimeType,
        string $audioLanguage,
        string $summaryLanguage
    ): array {
        if ($this->apiKey === '') {
            return [
                'ok'         => false,
                'error'      => 'Gemini API Key is missing. Please configure it in Settings.',
                'transcript' => '',
                'summary'    => [],
            ];
        }

        if (!is_file($filePath) || !is_readable($filePath)) {
            return [
                'ok'         => false,
                'error'      => 'Temporary recording file could not be read for processing.',
                'transcript' => '',
                'summary'    => [],
            ];
        }

        $fileSize = (int)filesize($filePath);$prompt = $this->buildPrompt($audioLanguage, $summaryLanguage);$remoteFileName = null;

        try {
            if ($fileSize <=$this->inlineLimit) {
                $rawBytes = file_get_contents($filePath);
                if ($rawBytes === false) { return [ 'ok'         => false, 'error'      => 'Failed to read recording bytes.', 'transcript' => '', 'summary'    => [], ]; }$mediaPart = [
                    'inlineData' => [
                        'mimeType' => $mimeType,
                        'data'     => base64_encode($rawBytes),
                    ],
                ];
            } else {
                $uploadRes = $this->uploadViaFilesApi($filePath, $mimeType,$fileSize);
                if (!$uploadRes['ok']) {
                    return [
                        'ok'         => false,
                        'error'      => $uploadRes['error'],
                        'transcript' => '',
                        'summary'    => [],
                    ];
                }
                $remoteFileName = $uploadRes['name'];$mediaPart = [
                    'fileData' => [
                        'mimeType' => $mimeType,
                        'fileUri'  => $uploadRes['uri'],
                    ],
                ];
            }

            $url = $this->endpoint . '/models/' . rawurlencode($this->model) . ':generateContent?key=' . urlencode($this->apiKey);$payload = [
                'contents' => [
                    [
                        'parts' => [
                            $mediaPart,
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature'      => 0.2,
                ],
            ];

            $genRes =$this->curlJson($url, 'POST',$payload, ['Content-Type: application/json'], 240);
            if (!$genRes['ok']) {
                return [
                    'ok'         => false,
                    'error'      => $genRes['error'],
                    'transcript' => '',
                    'summary'    => [],
                ];
            }

            $rawText = $this->extractTextFromResponse($genRes['data']);
            if ($rawText === '') {
                return [
                    'ok'         => false,
                    'error'      => 'Gemini returned an empty analysis response.',
                    'transcript' => '',
                    'summary'    => [],
                ];
            }

            $parsed =$this->parseStructuredOutput($rawText,$summaryLanguage);
            if (!$parsed['ok']) {
                return [
                    'ok'         => false,
                    'error'      => $parsed['error'],
                    'transcript' => '',
                    'summary'    => [],
                ];
            }

            return [
                'ok'         => true,
                'error'      => '',
                'transcript' => $parsed['transcript'],
                'summary'    => $parsed['summary'],
            ];
        } finally {
            if (is_string($remoteFileName) &&$remoteFileName !== '') {
                $this->deleteRemoteFile($remoteFileName);
            }
        }
    }

    /**
     * @return array{ok: bool, error: string, uri: string, name: string}
     */
    private function uploadViaFilesApi(string $filePath, string $mimeType, int$fileSize): array
    {
        $baseHost = preg_replace('#/v1/?$#', '', $this->endpoint) ?: 'https://generativelanguage.googleapis.com';$initUrl = $baseHost . '/upload/v1beta/files?key=' . urlencode($this->apiKey);

        $initHeaders = [
            'Content-Type: application/json',
            'X-Goog-Upload-Protocol: resumable',
            'X-Goog-Upload-Command: start',
            'X-Goog-Upload-Header-Content-Length: ' . $fileSize,
            'X-Goog-Upload-Header-Content-Type: ' . $mimeType,
        ];
        $initBody = [
            'file' => [
                'displayName' => basename($filePath),
            ],
        ];

        $ch = curl_init($initUrl);$respHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($initBody),
            CURLOPT_HTTPHEADER     => $initHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $rawResp = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($rawResp === false || $curlErr !== '') {
            return ['ok' => false, 'error' => 'Gemini Files upload init failed: ' . $curlErr, 'uri' => '', 'name' => ''];
        }

        $headerBlock = substr((string)$rawResp, 0,$headerSize);
        foreach (explode("\r\n", $headerBlock) as$hLine) {
            if (stripos($hLine, 'X-Goog-Upload-URL:') === 0) {
                $respHeaders['upload_url'] = trim(substr($hLine, strlen('X-Goog-Upload-URL:')));
            }
        }

        if ($httpCode < 200 || $httpCode >= 300 || empty($respHeaders['upload_url'])) {
            return [
                'ok'    => false,
                'error' => 'Gemini Files resumable session failed (HTTP ' . $httpCode . ').',
                'uri'   => '',
                'name'  => '',
            ];
        }

        $uploadUrl =$respHeaders['upload_url'];
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return ['ok' => false, 'error' => 'Unable to open file stream for resumable upload.', 'uri' => '', 'name' => ''];
        }

        $ch2 = curl_init($uploadUrl);
        curl_setopt_array($ch2, [
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_INFILE         => $handle,
            CURLOPT_INFILESIZE     => $fileSize,
            CURLOPT_UPLOAD         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Length: ' . $fileSize,
                'X-Goog-Upload-Offset: 0',
                'X-Goog-Upload-Command: upload, finalize',
            ],
            CURLOPT_TIMEOUT        => 240,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $uploadBody = curl_exec($ch2);
        $uploadErr = curl_error($ch2);
        $uploadCode = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        fclose($handle);

        if ($uploadBody === false || $uploadErr !== '' || $uploadCode < 200 || $uploadCode >= 300) {
            return [
                'ok'    => false,
                'error' => 'Gemini Files upload failed (HTTP ' . $uploadCode . '): ' .$uploadErr,
                'uri'   => '',
                'name'  => '',
            ];
        }

        $decoded = json_decode((string)$uploadBody, true);
        $fileUri = (string)($decoded['file']['uri'] ?? $decoded['uri'] ?? '');$fileName = (string)($decoded['file']['name'] ?? $decoded['name'] ?? '');

        if ($fileUri === '') {
            return ['ok' => false, 'error' => 'Gemini Files upload succeeded without returning a file URI.', 'uri' => '', 'name' => ''];
        }

        return ['ok' => true, 'error' => '', 'uri' => $fileUri, 'name' =>$fileName];
    }

    public function deleteRemoteFile(string $remoteFileName): void
    {
        $baseHost = preg_replace('#/v1/?$#', '', $this->endpoint) ?: 'https://generativelanguage.googleapis.com';
        $cleanName = ltrim($remoteFileName, '/');
        if (!str_starts_with($cleanName, 'files/')) {
            $cleanName = 'files/' .$cleanName;
        }
        $url =$baseHost . '/v1beta/' . $cleanName . '?key=' . urlencode($this->apiKey);
        $this->curlJson($url, 'DELETE', null, [], 30);
    }

    private function buildPrompt(string $audioLanguage, string$summaryLanguage): string
    {
        $audioLangInstruction = match ($audioLanguage) {
            'Chinese' => 'The primary spoken language is Chinese (Mandarin/Cantonese as spoken), though mixed/multilingual speech may occur.',
            'English' => 'The primary spoken language is English, though mixed/multilingual speech may occur.',
            'Malay'   => 'The primary spoken language is Malay, though mixed/multilingual speech may occur.',
            default   => 'Automatically detect all spoken languages in the recording, including multilingual or code-switched speech.',
        };

        $summaryLangInstruction = match ($summaryLanguage) {
            'English' => 'Write ALL seven summary sections strictly in clear, natural English.',
            'Malay'   => 'Write ALL seven summary sections strictly in clear, natural Malay (Bahasa Melayu).',
            default   => 'Write ALL seven summary sections strictly in clear, natural Chinese (简体中文).',
        };

        return <<<PROMPT
You are an expert audio and video analyst. Listen to or watch the entire recording from start to finish without skipping or omitting spoken content.

AUDIO LANGUAGE INSTRUCTION:
{$audioLangInstruction}

TRANSCRIPTION REQUIREMENTS:
- Produce a complete, verbatim transcript covering the entire recording from beginning to end.
- Identify spoken languages and handle multilingual / mixed-language speech accurately.
- Preserve technical terminology, proper names, dates, numbers, measurements, and important details.
- Do not intentionally omit spoken content or summarize inside the transcript.

SUMMARY LANGUAGE INSTRUCTION:
{$summaryLangInstruction}

Return ONLY a valid JSON object matching this exact schema:
{
  "transcript": "Full verbatim transcript string",
  "summary": {
    "overall_summary": "Comprehensive paragraph explaining the overall content and purpose of the recording",
    "main_topics": ["Topic 1", "Topic 2"],
    "important_points": ["Important point 1", "Important point 2"],
    "key_metrics_references": ["Metric, date, number, technical detail, or reference 1"],
    "decisions_conclusions": ["Decision or conclusion 1"],
    "action_items": ["Action item 1 (include owner/deadline only if explicitly mentioned)"],
    "high_level_synthesis": "Final high-level summary combining key takeaways and next steps"
  }
}

If no formal decisions/conclusions are mentioned, return an empty array [] for "decisions_conclusions".
If no action items are identified, return an empty array [] for "action_items".
Do not wrap the response in markdown code fences.
PROMPT;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractTextFromResponse(array $data): string
    {
        $parts =$data['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts)) { return ''; }$chunks = [];
        foreach ($parts as$part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $chunks[] =$part['text'];
            }
        }
        return implode("\n", $chunks);
    }

    /**
     * @return array{ok: bool, error: string, transcript: string, summary: array<string, mixed>}
     */
    private function parseStructuredOutput(string $rawText, string$summaryLanguage): array
    {
        $cleaned = trim($rawText);
        if (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```[a-zA-Z]*\s*/', '', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/\s*```$/', '', $cleaned) ?? $cleaned;
            $cleaned = trim($cleaned);
        }

        $decoded = json_decode($cleaned, true);
        if (!is_array($decoded)) {
            $start = strpos($cleaned, '{');
            $end = strrpos($cleaned, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $jsonSlice = substr($cleaned, $start, $end - $start + 1);
                $decoded = json_decode($jsonSlice, true);
            }
        }

        if (!is_array($decoded)) {
            return [
                'ok'         => false,
                'error'      => 'Failed to parse structured JSON response from Gemini.',
                'transcript' => '',
                'summary'    => [],
            ];
        }

        $transcript = trim((string)($decoded['transcript'] ?? ''));
        $rawSummary = is_array($decoded['summary'] ?? null) ? $decoded['summary'] : $decoded;

        $emptyDecisionsMsg = match ($summaryLanguage) {
            'English' => 'No formal decisions or conclusions were mentioned in the recording.',
            'Malay'   => 'Tiada keputusan atau kesimpulan rasmi dinyatakan dalam rakaman.',
            default   => '录音中未提及正式决定或结论。',
        };

        $emptyActionsMsg = match ($summaryLanguage) {
            'English' => 'No specific action items or next steps were identified in the recording.',
            'Malay'   => 'Tiada tindakan susulan khusus dikenal pasti dalam rakaman.',
            default   => '录音中未识别出明确的行动项或后续步骤。',
        };

        $summary = [
            'overall_summary'        => $this->normalizeText($rawSummary['overall_summary'] ?? ''),
            'main_topics'          => $this->normalizeList($rawSummary['main_topics'] ?? []),
            'important_points'     => $this->normalizeList($rawSummary['important_points'] ?? []),
            'key_metrics_references' => $this->normalizeList($rawSummary['key_metrics_references'] ?? []),
            'decisions_conclusions'  => $this->normalizeList($rawSummary['decisions_conclusions'] ?? []),
            'action_items'         => $this->normalizeList($rawSummary['action_items'] ?? []),
            'high_level_synthesis' => $this->normalizeText($rawSummary['high_level_synthesis'] ?? ''),
            'empty_decisions_text' => $emptyDecisionsMsg,
            'empty_actions_text'   => $emptyActionsMsg,
        ];

        if ($transcript === '' && $summary['overall_summary'] === '') {
            return [
                'ok'         => false,
                'error'      => 'Gemini returned an empty transcript and summary.',
                'transcript' => '',
                'summary'    => [],
            ];
        }

        return [
            'ok'         => true,
            'error'      => '',
            'transcript' => $transcript,
            'summary'    => $summary,
        ];
    }

    private function normalizeText(mixed $val): string
    {
        if (is_string($val)) {
            return trim($val);
        }
        if (is_array($val)) {
            $items = [];
            foreach ($val as $item) {
                if (is_scalar($item) && trim((string)$item) !== '') {
                    $items[] = trim((string)$item);
                }
            }
            return implode(' ', $items);
        }
        return is_scalar($val) ? trim((string)$val) : '';
    }

    /**
     * @return array<int, string>
     */
    private function normalizeList(mixed $val): array
    {
        if (is_array($val)) {
            $out = [];
            foreach ($val as $item) {
                if (is_scalar($item)) {
                    $str = trim((string)$item);
                    if ($str !== '') {
                        $out[] = $str;
                    }
                } elseif (is_array($item)) {
                    $joined = implode(' - ', array_filter(array_map(
                        static fn($v) => is_scalar($v) ? trim((string)$v) : '',
                        $item
                    )));
                    if ($joined !== '') {
                        $out[] = $joined;
                    }
                }
            }
            return $out;
        }
        if (is_string($val) && trim($val) !== '') {
            $lines = preg_split('/\r\n|\r|\n/', trim($val)) ?: [];
            $out = [];
            foreach ($lines as $line) {
                $clean = preg_replace('/^[\-\*•\d.)]+\s*/', '', trim($line)) ?? trim($line);
                if ($clean !== '') {
                    $out[] = $clean;
                }
            }
            return $out;
        }
        return [];
    }

    /**
     * @param array<int, string> $headers
     * @return array{ok: bool, error: string, data: array<string, mixed>}
     */
    private function curlJson(string $url, string $method, ?array $payload, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            return ['ok' => false, 'error' => 'Network/cURL error: ' . $err, 'data' => []];
        }

        $decoded = json_decode((string)$raw, true);
        if ($code < 200 || $code >= 300) {
            $apiMsg = is_array($decoded) ? (string)($decoded['error']['message'] ?? '') : '';
            $detail = $apiMsg !== '' ? $apiMsg : ('HTTP status ' . $code);
            return ['ok' => false, 'error' => 'Gemini API error: ' . $detail, 'data' => is_array($decoded) ? $decoded : []];
        }

        return ['ok' => true, 'error' => '', 'data' => is_array($decoded) ? $decoded : []];
    }
}