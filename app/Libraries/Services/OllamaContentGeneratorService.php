<?php

namespace App\Libraries\Services;

use App\Libraries\Contracts\ContentGeneratorInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\ContentPipeline;
use Psr\Log\LoggerInterface;
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
            // Allow per-request model override via payload; fall back to config
            $model = (string) ($payload['ollama_model'] ?? '');
            if ($model === '') {
                $model = $this->config->ollamaModel;
            }

            $requestPayload = [
                'model' => $model,
                'stream' => false,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Kamu adalah penulis konten SEO expert. Jawab dalam JSON valid saja tanpa teks tambahan.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildPrompt($payload),
                    ],
                ],
            ];

            $response = $this->http->post(rtrim($baseUrl, '/') . '/api/chat', [
                'timeout' => $this->config->requestTimeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($requestPayload, JSON_UNESCAPED_UNICODE),
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                $snippet = mb_substr($response->getBody(), 0, 300);

                return [
                    'success' => false,
                    'article' => null,
                    'error_code' => 'OLLAMA_HTTP_ERROR',
                    'error_message' => 'Ollama request gagal dengan status ' . $response->getStatusCode() . '. ' . $snippet,
                ];
            }

            $decoded = json_decode($response->getBody(), true);
            $content = $decoded['message']['content'] ?? null;

            if (! is_string($content) || trim($content) === '') {
                return [
                    'success' => false,
                    'article' => null,
                    'error_code' => 'OLLAMA_EMPTY_RESPONSE',
                    'error_message' => 'Response Ollama kosong.',
                ];
            }

            $articleData = $this->parseJsonPayload($content);
            if ($articleData === null) {
                return [
                    'success' => false,
                    'article' => null,
                    'error_code' => 'OLLAMA_INVALID_JSON',
                    'error_message' => 'Format output Ollama tidak valid JSON.',
                ];
            }

            return [
                'success' => true,
                'article' => $this->normalizeArticle($articleData, $payload),
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            $this->logger->error('Ollama generation failed.', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'article' => null,
                'error_code' => 'OLLAMA_REQUEST_EXCEPTION',
                'error_message' => 'Terjadi error saat memanggil Ollama: ' . $e->getMessage(),
            ];
        }
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

        return "Buat artikel blog SEO-friendly berbahasa Indonesia.\n"
            . "Gaya artikel: {$articleStyle}.\n"
            . "Tone penulisan: {$tone}.\n"
            . "Panjang target {$wordTarget} kata.\n"
            . "Gunakan struktur H1-H3, sertakan FAQ.\n"
            . "Kembalikan JSON dengan field:"
            . " title, body_html, body_markdown, word_count, language, tone, primary_keyword, secondary_keywords (array), seo_title, seo_meta_description, seo_slug, faq (array berisi question+answer), seo_score.\n"
            . "Context title: {$title}\n"
            . "Context description: {$description}\n"
            . "Prompt user:\n{$promptText}\n"
            . "Transcript (jika tersedia):\n{$transcript}";
    }

    private function parseJsonPayload(string $content): ?array
    {
        $trimmed = trim($content);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{(?:.|\R)*\}/u', $trimmed, $matches) === 1) {
            $decoded = json_decode($matches[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
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
