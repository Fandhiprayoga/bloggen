<?php

namespace App\Libraries\Services;

use App\Libraries\Contracts\ContentGeneratorInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\ContentPipeline;
use Psr\Log\LoggerInterface;
use Throwable;

class OpenAIContentGeneratorService implements ContentGeneratorInterface
{
    public function __construct(
        private readonly ContentPipeline $config,
        private readonly CURLRequest $http,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(array $payload): array
    {
        if ($this->config->openAIKey === '') {
            return [
                'success' => false,
                'article' => null,
                'error_code' => 'OPENAI_KEY_MISSING',
                'error_message' => 'OpenAI API key belum dikonfigurasi di env.',
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
            $requestPayload = [
                'model' => $this->config->openAIModel,
                'temperature' => 0.4,
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

            $response = $this->http->post(rtrim($this->config->openAIBaseUrl, '/') . '/chat/completions', [
                'timeout' => $this->config->requestTimeout,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->openAIKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($requestPayload, JSON_UNESCAPED_UNICODE),
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                $body = $response->getBody();
                $snippet = mb_substr($body, 0, 300);

                return [
                    'success' => false,
                    'article' => null,
                    'error_code' => 'OPENAI_HTTP_ERROR',
                    'error_message' => 'OpenAI request gagal dengan status ' . $response->getStatusCode() . '. ' . $snippet,
                ];
            }

            $decoded = json_decode($response->getBody(), true);
            $content = $decoded['choices'][0]['message']['content'] ?? null;

            if (! is_string($content) || trim($content) === '') {
                return [
                    'success' => false,
                    'article' => null,
                    'error_code' => 'OPENAI_EMPTY_RESPONSE',
                    'error_message' => 'Response OpenAI kosong.',
                ];
            }

            $articleData = $this->parseJsonPayload($content);
            if ($articleData === null) {
                return [
                    'success' => false,
                    'article' => null,
                    'error_code' => 'OPENAI_INVALID_JSON',
                    'error_message' => 'Format output OpenAI tidak valid JSON.',
                ];
            }

            $normalized = $this->normalizeArticle($articleData, $payload);

            return [
                'success' => true,
                'article' => $normalized,
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            $this->logger->error('OpenAI generation failed.', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'article' => null,
                'error_code' => 'OPENAI_REQUEST_EXCEPTION',
                'error_message' => 'Terjadi error saat memanggil OpenAI: ' . $e->getMessage(),
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
            . "Jika konteks berasal dari ringkasan/transkrip video, jadikan hanya sebagai bahan riset internal."
            . " DILARANG menyebut video, channel, YouTube, narasumber video, adegan video, atau timestamp/timecode seperti 00:10."
            . " Artikel wajib terasa sebagai artikel baru yang mandiri dan tidak bergantung pada video sumber.\n"
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
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug) ?? $slug;
        $slug = preg_replace('/[\s-]+/', '-', $slug) ?? $slug;

        return trim($slug, '-') !== '' ? trim($slug, '-') : 'artikel-youtube';
    }
}
