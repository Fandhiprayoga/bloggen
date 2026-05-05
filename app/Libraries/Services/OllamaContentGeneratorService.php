<?php

namespace App\Libraries\Services;

use App\Libraries\Contracts\ContentGeneratorInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\ContentPipeline;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class OllamaContentGeneratorService implements ContentGeneratorInterface
{
    public function __construct(
        private readonly ContentPipeline $config,
        private readonly CURLRequest $http,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(array $payload): array
    {
        $baseUrl = $this->config->ollamaBaseUrl;
        $effectiveTimeout = $this->resolveHttpTimeout((string) ($payload['word_target'] ?? ''), 1);
        if ($baseUrl === '') {
            return [
                'success' => false,
                'article' => null,
                'error_code' => 'OLLAMA_URL_MISSING',
                'error_message' => 'Ollama base URL belum dikonfigurasi di env (content.ollamaBaseUrl).',
            ];
        }

        $hasTranscript = ! empty($payload['transcript']) && is_string($payload['transcript']);
        $hasPromptText = ! empty($payload['prompt_text']) && is_string($payload['prompt_text']);

        if (! $hasTranscript && ! $hasPromptText) {
            return [
                'success' => false,
                'article' => null,
                'error_code' => 'CONTENT_CONTEXT_REQUIRED',
                'error_message' => 'Minimal transcript atau prompt text wajib tersedia sebelum generate konten.',
            ];
        }

        try {
            $attemptPayload = $payload;
            $maxAttempts = 2;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $attemptTimeout = $this->resolveHttpTimeout((string) ($attemptPayload['word_target'] ?? ''), $attempt);
                $effectiveTimeout = $attemptTimeout;

                // Allow per-request model override via payload; fall back to config
                $model = (string) ($attemptPayload['ollama_model'] ?? '');
                if ($model === '') {
                    $model = $this->config->ollamaModel;
                }

                $requestPayload = [
                    'model' => $model,
                    'stream' => true,
                    'format' => 'json',
                    'options' => [
                        'temperature' => 0,
                        'num_predict' => $this->resolveNumPredict((string) ($attemptPayload['word_target'] ?? ''), $attempt),
                    ],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Kamu adalah penulis konten SEO expert berbahasa Indonesia. Jawab HANYA dengan valid JSON tanpa teks tambahan di awal atau akhir.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->buildPrompt($attemptPayload),
                        ],
                    ],
                ];

                $onChunk = $attemptPayload['on_chunk'] ?? null;
                $rawBody = '';
                $statusCode = 0;

                // Retry run disables chunk callbacks to avoid duplicate streaming output in UI.
                if (is_callable($onChunk) && $attempt === 1) {
                    $streamResult = $this->postStreamWithNativeCurl(
                        rtrim($baseUrl, '/') . '/api/chat',
                        $requestPayload,
                        $attemptTimeout,
                        $onChunk,
                    );
                    $rawBody = $streamResult['body'];
                    $statusCode = $streamResult['status_code'];
                } else {
                    $response = $this->http->post(rtrim($baseUrl, '/') . '/api/chat', [
                        'timeout' => $attemptTimeout,
                        'connect_timeout' => min(10, $attemptTimeout),
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'body' => json_encode($requestPayload, JSON_UNESCAPED_UNICODE),
                    ]);
                    $rawBody = (string) $response->getBody();
                    $statusCode = (int) $response->getStatusCode();
                }

                if ($statusCode < 200 || $statusCode >= 300) {
                    $snippet = mb_substr($rawBody, 0, 300);

                    return [
                        'success' => false,
                        'article' => null,
                        'error_code' => 'OLLAMA_HTTP_ERROR',
                        'error_message' => 'Ollama request gagal dengan status ' . $statusCode . '. ' . $snippet,
                    ];
                }

                $content = $this->extractResponseContent($rawBody);

                if (! is_string($content) || trim($content) === '') {
                    return [
                        'success' => false,
                        'article' => null,
                        'error_code' => 'OLLAMA_EMPTY_RESPONSE',
                        'error_message' => 'Response Ollama kosong.',
                    ];
                }

                $articleData = $this->parseJsonPayload($content);
                if ($articleData !== null) {
                    return [
                        'success' => true,
                        'article' => $this->normalizeArticle($articleData, $attemptPayload),
                        'error_code' => null,
                        'error_message' => null,
                    ];
                }

                $isTruncated = $this->looksLikeTruncatedJson($content);

                $this->logger->error('Ollama JSON parsing failed', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'is_truncated' => $isTruncated,
                    'raw_content' => mb_substr($content, 0, 500),
                    'content_length' => strlen($content),
                ]);

                if ($isTruncated && $attempt < $maxAttempts) {
                    $attemptPayload = $this->buildRetryPayloadForTruncatedJson($attemptPayload);
                    $attemptPayload['on_chunk'] = null;
                    continue;
                }

                if ($isTruncated) {
                    return [
                        'success' => false,
                        'article' => null,
                        'error_code' => 'OLLAMA_TRUNCATED_JSON',
                        'error_message' => 'Output Ollama terpotong sebelum JSON selesai meskipun sudah retry otomatis. Coba naikkan content.requestTimeout, gunakan model dengan context lebih besar, atau kurangi target kata.',
                    ];
                }

                return [
                    'success' => false,
                    'article' => null,
                    'error_code' => 'OLLAMA_INVALID_JSON',
                    'error_message' => 'Format output Ollama tidak valid JSON. Response: ' . mb_substr($content, 0, 200),
                ];
            }

            return [
                'success' => false,
                'article' => null,
                'error_code' => 'OLLAMA_TRUNCATED_JSON',
                'error_message' => 'Output Ollama terpotong sebelum JSON selesai.',
            ];
        } catch (Throwable $e) {
            $this->logger->error('Ollama generation failed.', [
                'message' => $e->getMessage(),
                'effective_timeout' => $effectiveTimeout,
                'configured_timeout' => $this->config->requestTimeout,
            ]);

            return [
                'success' => false,
                'article' => null,
                'error_code' => 'OLLAMA_REQUEST_EXCEPTION',
                'error_message' => 'Terjadi error saat memanggil Ollama: ' . $e->getMessage(),
            ];
        }
    }

    private function resolveHttpTimeout(string $wordTarget = '', int $attempt = 1): int
    {
        $configuredTimeout = max(5, (int) $this->config->requestTimeout);
        $recommendedTimeout = match ($wordTarget) {
            '1800-2500' => 240,
            '1200-1800' => 180,
            default => 120,
        };
        if ($attempt > 1) {
            $recommendedTimeout += 60;
        }

        $targetTimeout = max($configuredTimeout, $recommendedTimeout);
        $maxExecution = (int) ini_get('max_execution_time');

        // max_execution_time = 0 means unlimited; keep computed timeout in that case.
        if ($maxExecution <= 0) {
            return $targetTimeout;
        }

        // Keep a safety buffer so cURL timeout happens before PHP kills the request.
        $safeLimit = max(5, $maxExecution - 15);

        return min($targetTimeout, $safeLimit);
    }

    private function buildPrompt(array $payload): string
    {
        $title = (string) ($payload['title'] ?? '');
        $description = (string) ($payload['description'] ?? '');
        $transcript = (string) ($payload['transcript'] ?? '');
        $promptText = (string) ($payload['prompt_text'] ?? '');
        $articleStyle = (string) ($payload['article_style'] ?? 'informative');
        $tone = (string) ($payload['tone'] ?? 'santai-edukatif');
        $wordTarget = (string) ($payload['word_target'] ?? '1200-1800');

        return "Kamu adalah penulis konten SEO expert bahasa Indonesia. PENTING: Jawab HANYA dengan JSON valid, tanpa kata-kata tambahan di awal atau akhir.\n\n"
            . "Buat artikel blog SEO-friendly berbahasa Indonesia.\n"
            . "- Gaya artikel: {$articleStyle}\n"
            . "- Tone penulisan: {$tone}\n"
            . "- Panjang target {$wordTarget} kata\n"
            . "- Gunakan struktur H1-H3, sertakan FAQ\n\n"
            . "ATURAN SUMBER VIDEO (WAJIB):\n"
            . "- Jika context berasal dari ringkasan/transkrip video, gunakan hanya sebagai riset internal.\n"
            . "- DILARANG menyebut video, channel, YouTube, narasumber video, adegan video, atau timestamp/timecode (contoh: 00:10).\n"
            . "- Tulis artikel baru yang sepenuhnya mandiri dan tidak bergantung pada video sumber.\n\n"
            . "WAJIB RETURN EXACT JSON STRUCTURE:\n"
            . "{\n"
            . "  \"title\": \"...\",\n"
            . "  \"body_html\": \"...\",\n"
            . "  \"body_markdown\": \"...\",\n"
            . "  \"word_count\": 1200,\n"
            . "  \"language\": \"id\",\n"
            . "  \"tone\": \"{$tone}\",\n"
            . "  \"primary_keyword\": \"...\",\n"
            . "  \"secondary_keywords\": [\"...\"],\n"
            . "  \"seo_title\": \"...\",\n"
            . "  \"seo_meta_description\": \"...\",\n"
            . "  \"seo_slug\": \"...\",\n"
            . "  \"faq\": [{\"question\": \"...\", \"answer\": \"...\"}],\n"
            . "  \"seo_score\": 85\n"
            . "}\n\n"
            . "Context title: {$title}\n"
            . "Context description: {$description}\n"
            . "Prompt user:\n{$promptText}\n"
            . "Transcript (jika tersedia):\n{$transcript}";
    }

    private function extractResponseContent(string $rawBody): ?string
    {
        $trimmed = trim($rawBody);
        if ($trimmed === '') {
            return null;
        }

        // Non-stream fallback: single JSON object response.
        $decoded = json_decode($trimmed, true);
        $singleContent = $decoded['message']['content'] ?? null;
        if (is_string($singleContent) && trim($singleContent) !== '') {
            return $singleContent;
        }

        // Stream response from Ollama is newline-delimited JSON (NDJSON).
        $parts = preg_split('/\R+/', $trimmed) ?: [];
        $chunks = [];
        foreach ($parts as $part) {
            $line = trim((string) $part);
            if ($line === '') {
                continue;
            }

            $item = json_decode($line, true);
            if (! is_array($item)) {
                continue;
            }

            $piece = $item['message']['content'] ?? null;
            if (is_string($piece) && $piece !== '') {
                $chunks[] = $piece;
            }
        }

        if ($chunks === []) {
            return null;
        }

        return implode('', $chunks);
    }

    /**
     * @param callable(string):bool|void $onChunk Return false to stop stream early.
     * @return array{status_code:int,body:string}
     */
    private function postStreamWithNativeCurl(string $url, array $requestPayload, int $httpTimeout, callable $onChunk): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Gagal inisialisasi cURL.');
        }

        $body = '';
        $lineBuffer = '';

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($requestPayload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => false,
            // Avoid hard total timeout while stream is active; rely on low-speed timeout for stalled transfers.
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CONNECTTIMEOUT => min(10, $httpTimeout),
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => max(30, min(120, $httpTimeout)),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use (&$body, &$lineBuffer, $onChunk): int {
                $body .= $chunk;
                $lineBuffer .= $chunk;

                while (($pos = strpos($lineBuffer, "\n")) !== false) {
                    $line = trim(substr($lineBuffer, 0, $pos));
                    $lineBuffer = substr($lineBuffer, $pos + 1);

                    if ($line === '') {
                        continue;
                    }

                    $decoded = json_decode($line, true);
                    $piece = $decoded['message']['content'] ?? null;
                    if (is_string($piece) && $piece !== '') {
                        $result = $onChunk($piece);
                        if ($result === false) {
                            return 0;
                        }
                    }
                }

                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);

        if ($lineBuffer !== '') {
            $decoded = json_decode(trim($lineBuffer), true);
            $piece = $decoded['message']['content'] ?? null;
            if (is_string($piece) && $piece !== '') {
                $result = $onChunk($piece);
                if ($result === false) {
                    curl_close($ch);

                    return [
                        'status_code' => 499,
                        'body' => $body,
                    ];
                }
            }
        }

        if ($ok === false) {
            $message = curl_error($ch);
            curl_close($ch);

            throw new RuntimeException('cURL stream error: ' . $message);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'status_code' => $statusCode,
            'body' => $body,
        ];
    }

    private function parseJsonPayload(string $content): ?array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }

        $candidates = [];
        $candidates[] = $trimmed;

        $withoutFence = $this->stripCodeFence($trimmed);
        if ($withoutFence !== $trimmed) {
            $candidates[] = $withoutFence;
        }

        $extracted = $this->extractFirstJsonObject($trimmed);
        if ($extracted !== null) {
            $candidates[] = $extracted;
        }

        if ($withoutFence !== '') {
            $extractedFromFence = $this->extractFirstJsonObject($withoutFence);
            if ($extractedFromFence !== null) {
                $candidates[] = $extractedFromFence;
            }
        }

        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function stripCodeFence(string $content): string
    {
        $trimmed = trim($content);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $trimmed, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/i', $trimmed, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        // Fallback: remove opening/closing fences even when format is malformed.
        $withoutOpenFence = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
        $withoutAnyFence = preg_replace('/\s*```\s*$/', '', $withoutOpenFence) ?? $withoutOpenFence;

        return trim($withoutAnyFence);
    }

    private function extractFirstJsonObject(string $content): ?string
    {
        $start = strpos($content, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($content);

        for ($i = $start; $i < $length; $i++) {
            $ch = $content[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($ch === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($ch === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }

            if ($ch === '{') {
                $depth++;
                continue;
            }

            if ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $start, ($i - $start) + 1);
                }
            }
        }

        return null;
    }

    private function looksLikeTruncatedJson(string $content): bool
    {
        $candidate = $this->stripCodeFence(trim($content));
        if ($candidate === '') {
            return false;
        }

        $start = strpos($candidate, '{');
        if ($start === false) {
            return false;
        }

        $candidate = substr($candidate, $start);

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($candidate);

        for ($i = 0; $i < $length; $i++) {
            $ch = $candidate[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($ch === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($ch === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }

            if ($ch === '{') {
                $depth++;
                continue;
            }

            if ($ch === '}') {
                $depth--;
            }
        }

        return $depth > 0 || $inString;
    }

    private function buildRetryPayloadForTruncatedJson(array $payload): array
    {
        $currentTarget = (string) ($payload['word_target'] ?? '1200-1800');
        $retryTarget = match ($currentTarget) {
            '1800-2500' => '1200-1800',
            '1200-1800' => '800-1200',
            default => '800-1200',
        };

        $payload['word_target'] = $retryTarget;

        return $payload;
    }

    private function resolveNumPredict(string $wordTarget, int $attempt): int
    {
        $base = match ($wordTarget) {
            '1800-2500' => 12288,
            '1200-1800' => 8192,
            default => 6144,
        };

        if ($attempt > 1) {
            $base += 2048;
        }

        return min(16384, $base);
    }

    private function normalizeArticle(array $article, array $payload): array
    {
        $bodyHtml = (string) ($article['body_html'] ?? '');
        $bodyMarkdown = (string) ($article['body_markdown'] ?? '');
        $title = (string) ($article['title'] ?? ($payload['title'] ?? 'Artikel dari Prompt'));

        $textForCount = $bodyMarkdown !== '' ? $bodyMarkdown : strip_tags($bodyHtml);
        $wordCount = $this->countWords($textForCount);
        $seoSlug = (string) ($article['seo_slug'] ?? $this->slugify($title));

        return [
            'title' => $title,
            'body_html' => $bodyHtml,
            'body_markdown' => $bodyMarkdown,
            'word_count' => (int) ($article['word_count'] ?? $wordCount),
            'language' => (string) ($article['language'] ?? 'id'),
            'tone' => (string) ($article['tone'] ?? ($payload['tone'] ?? 'santai-edukatif')),
            'primary_keyword' => (string) ($article['primary_keyword'] ?? ''),
            'secondary_keywords' => is_array($article['secondary_keywords'] ?? null) ? $article['secondary_keywords'] : [],
            'seo_title' => (string) ($article['seo_title'] ?? $title),
            'seo_meta_description' => (string) ($article['seo_meta_description'] ?? ''),
            'seo_slug' => $seoSlug,
            'faq' => is_array($article['faq'] ?? null) ? $article['faq'] : [],
            'seo_score' => isset($article['seo_score']) ? (float) $article['seo_score'] : null,
        ];
    }

    private function countWords(string $text): int
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        if ($normalized === '') {
            return 0;
        }

        return count(explode(' ', $normalized));
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/u', '', $text) ?? $text;
        $text = preg_replace('/[\s_]+/', '-', $text) ?? $text;

        return trim($text, '-');
    }
}
