<?php

namespace App\Libraries\Services;

use App\Libraries\Contracts\ContentGeneratorInterface;
use App\Libraries\Services\OllamaContentGeneratorService;
use App\Libraries\Services\OpenAIContentGeneratorService;
use App\Libraries\Contracts\FeaturedImageProviderInterface;
use App\Models\ContentJobModel;
use App\Models\GeneratedArticleModel;
use App\Models\GeneratedImageModel;
use Psr\Log\LoggerInterface;
use Throwable;

class ContentPipelineOrchestrator
{
    public function __construct(
        private readonly ContentGeneratorInterface $contentGenerator,
        private readonly FeaturedImageProviderInterface $imageProvider,
        private readonly ContentJobModel $contentJobModel,
        private readonly GeneratedArticleModel $generatedArticleModel,
        private readonly GeneratedImageModel $generatedImageModel,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function runPromptGeneration(string $promptText, ?int $userId, array $options = []): array
    {
        $jobId = $this->uuidV4();
        $pseudoVideoId = substr(hash('sha256', $promptText), 0, 11);
        $articleStyle = (string) ($options['article_style'] ?? 'informative');
        $wordTarget = (string) ($options['word_target'] ?? '1200-1800');
        $tone = (string) ($options['tone'] ?? 'santai-edukatif');
        $aiProvider = (string) ($options['ai_provider'] ?? 'openai');
        $ollamaModel = (string) ($options['ollama_model'] ?? '');
        $generator = $this->resolveContentGenerator($aiProvider);

        $jobData = [
            'id' => $jobId,
            'user_id' => $userId,
            'source_type' => 'prompt',
            'youtube_url' => 'prompt://manual',
            'youtube_video_id' => $pseudoVideoId,
            'prompt_text' => $promptText,
            'status' => 'processing',
            'error_code' => null,
            'error_message' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->contentJobModel->insert($jobData);

        try {
            $this->contentJobModel->update($jobId, [
                'source_title' => 'Prompt-based Content Request',
                'source_description' => mb_substr($promptText, 0, 500),
                'source_comments_json' => json_encode([
                    'article_style' => $articleStyle,
                    'word_target' => $wordTarget,
                    'tone' => $tone,
                    'ai_provider' => $aiProvider,
                                    'ollama_model' => $ollamaModel ?: null,
                ], JSON_UNESCAPED_UNICODE),
                'transcript_text' => null,
            ]);

            $generationResult = $generator->generate([
                'prompt_text' => $promptText,
                'title' => null,
                'description' => null,
                'comments' => [],
                'article_style' => $articleStyle,
                'language' => 'id',
                'tone' => $tone,
                'word_target' => $wordTarget,
                'ollama_model' => $ollamaModel,
            ]);

            if (! ($generationResult['success'] ?? false)) {
                return $this->markFailed(
                    $jobId,
                    (string) ($generationResult['error_code'] ?? 'CONTENT_GENERATION_FAILED'),
                    (string) ($generationResult['error_message'] ?? 'Gagal membuat artikel.'),
                );
            }

            $article = is_array($generationResult['article'] ?? null) ? $generationResult['article'] : [];

            $this->generatedArticleModel->insert([
                'id' => $this->uuidV4(),
                'content_job_id' => $jobId,
                'article_title' => (string) ($article['title'] ?? 'Untitled Article'),
                'article_body_html' => (string) ($article['body_html'] ?? ''),
                'article_body_markdown' => (string) ($article['body_markdown'] ?? ''),
                'word_count' => (int) ($article['word_count'] ?? 0),
                'language' => (string) ($article['language'] ?? 'id'),
                'tone' => (string) ($article['tone'] ?? 'santai-edukatif'),
                'primary_keyword' => (string) ($article['primary_keyword'] ?? ''),
                'secondary_keywords_json' => json_encode($article['secondary_keywords'] ?? [], JSON_UNESCAPED_UNICODE),
                'seo_title' => (string) ($article['seo_title'] ?? ''),
                'seo_meta_description' => (string) ($article['seo_meta_description'] ?? ''),
                'seo_slug' => (string) ($article['seo_slug'] ?? ''),
                'faq_json' => json_encode($article['faq'] ?? [], JSON_UNESCAPED_UNICODE),
                'seo_score' => isset($article['seo_score']) ? (float) $article['seo_score'] : null,
                'is_edited_manually' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Featured image is NOT auto-generated here.
            // User can generate or upload image manually from the detail page.

            $this->contentJobModel->update($jobId, [
                'status' => 'ready',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'job_id' => $jobId,
            ];
        } catch (Throwable $e) {
            $this->logger->error('Content pipeline failed unexpectedly.', [
                'job_id' => $jobId,
                'message' => $e->getMessage(),
            ]);

            return $this->markFailed($jobId, 'UNEXPECTED_ERROR', 'Terjadi kesalahan tak terduga saat memproses job.');
        }
    }

    public function runGeneration(string $youtubeUrl, ?int $userId): array
    {
        return $this->runPromptGeneration($youtubeUrl, $userId, []);
    }

    public function regenerateArticle(string $jobId): array
    {
        $job = $this->contentJobModel->find($jobId);
        if (! is_array($job)) {
            return [
                'success' => false,
                'job_id' => $jobId,
                'error_code' => 'JOB_NOT_FOUND',
                'error_message' => 'Job tidak ditemukan.',
            ];
        }

        $promptText = trim((string) ($job['prompt_text'] ?? ''));
        if ($promptText === '') {
            return [
                'success' => false,
                'job_id' => $jobId,
                'error_code' => 'PROMPT_REQUIRED',
                'error_message' => 'Prompt job kosong, artikel tidak bisa diregenerate.',
            ];
        }

        $options = $this->extractGenerationOptions($job);
        $articleStyle = $options['article_style'];
        $wordTarget = $options['word_target'];
        $tone = $options['tone'];
        $aiProvider = $options['ai_provider'];
        $ollamaModel = $options['ollama_model'];
        $generator = $this->resolveContentGenerator($aiProvider);

        $this->contentJobModel->update($jobId, [
            'status' => 'processing',
            'error_code' => null,
            'error_message' => null,
            'source_comments_json' => json_encode([
                'article_style' => $articleStyle,
                'word_target' => $wordTarget,
                'tone' => $tone,
                'ai_provider' => $aiProvider,
                'ollama_model' => $ollamaModel ?: null,
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $generationResult = $generator->generate([
                'prompt_text' => $promptText,
                'title' => null,
                'description' => null,
                'comments' => [],
                'article_style' => $articleStyle,
                'language' => 'id',
                'tone' => $tone,
                'word_target' => $wordTarget,
                'ollama_model' => $ollamaModel,
            ]);

            if (! ($generationResult['success'] ?? false)) {
                return $this->markFailed(
                    $jobId,
                    (string) ($generationResult['error_code'] ?? 'CONTENT_REGENERATION_FAILED'),
                    (string) ($generationResult['error_message'] ?? 'Gagal regenerate artikel.'),
                );
            }

            $article = is_array($generationResult['article'] ?? null) ? $generationResult['article'] : [];
            $this->persistGeneratedArticle($jobId, $article);

            $this->contentJobModel->update($jobId, [
                'status' => 'ready',
                'error_code' => null,
                'error_message' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'job_id' => $jobId,
            ];
        } catch (Throwable $e) {
            $this->logger->error('Content regeneration failed unexpectedly.', [
                'job_id' => $jobId,
                'message' => $e->getMessage(),
            ]);

            return $this->markFailed($jobId, 'UNEXPECTED_ERROR', 'Terjadi kesalahan tak terduga saat regenerate artikel.');
        }
    }

    private function markFailed(string $jobId, string $errorCode, string $errorMessage): array
    {
        $this->contentJobModel->update($jobId, [
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'success' => false,
            'job_id' => $jobId,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ];
    }

    private function resolveContentGenerator(string $provider): ContentGeneratorInterface
    {
        if ($provider === 'ollama') {
            return new OllamaContentGeneratorService(
                config('ContentPipeline'),
                \Config\Services::curlrequest(),
                \Config\Services::logger(),
            );
        }

        return $this->contentGenerator; // default: OpenAI
    }

    /**
     * @return array{article_style:string,word_target:string,tone:string,ai_provider:string,ollama_model:string}
     */
    private function extractGenerationOptions(array $job): array
    {
        $raw = $job['source_comments_json'] ?? null;
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $aiProvider = (string) ($decoded['ai_provider'] ?? 'openai');
        if (! in_array($aiProvider, ['openai', 'ollama'], true)) {
            $aiProvider = 'openai';
        }

        return [
            'article_style' => (string) ($decoded['article_style'] ?? 'informative'),
            'word_target' => (string) ($decoded['word_target'] ?? '1200-1800'),
            'tone' => (string) ($decoded['tone'] ?? 'santai-edukatif'),
            'ai_provider' => $aiProvider,
            'ollama_model' => preg_replace('/[^a-zA-Z0-9:\-\.]+/', '', (string) ($decoded['ollama_model'] ?? '')) ?? '',
        ];
    }

    private function persistGeneratedArticle(string $jobId, array $article): void
    {
        $payload = [
            'content_job_id' => $jobId,
            'article_title' => (string) ($article['title'] ?? 'Untitled Article'),
            'article_body_html' => (string) ($article['body_html'] ?? ''),
            'article_body_markdown' => (string) ($article['body_markdown'] ?? ''),
            'word_count' => (int) ($article['word_count'] ?? 0),
            'language' => (string) ($article['language'] ?? 'id'),
            'tone' => (string) ($article['tone'] ?? 'santai-edukatif'),
            'primary_keyword' => (string) ($article['primary_keyword'] ?? ''),
            'secondary_keywords_json' => json_encode($article['secondary_keywords'] ?? [], JSON_UNESCAPED_UNICODE),
            'seo_title' => (string) ($article['seo_title'] ?? ''),
            'seo_meta_description' => (string) ($article['seo_meta_description'] ?? ''),
            'seo_slug' => (string) ($article['seo_slug'] ?? ''),
            'faq_json' => json_encode($article['faq'] ?? [], JSON_UNESCAPED_UNICODE),
            'seo_score' => isset($article['seo_score']) ? (float) $article['seo_score'] : null,
            'is_edited_manually' => false,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $existingArticle = $this->generatedArticleModel->where('content_job_id', $jobId)->first();
        if (is_array($existingArticle) && isset($existingArticle['id'])) {
            $this->generatedArticleModel->update((string) $existingArticle['id'], $payload);

            return;
        }

        $payload['id'] = $this->uuidV4();
        $payload['created_at'] = date('Y-m-d H:i:s');
        $this->generatedArticleModel->insert($payload);
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
