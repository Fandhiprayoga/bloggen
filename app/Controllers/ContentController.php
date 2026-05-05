<?php

namespace App\Controllers;

use App\Libraries\Services\ContentPipelineOrchestrator;
use App\Models\ContentJobModel;
use App\Models\GeneratedArticleModel;
use App\Models\GeneratedImageModel;
use App\Models\WordpressSubmissionModel;
use App\Models\WordpressTaxonomyModel;
use Config\ContentPipeline;
use RuntimeException;

class ContentController extends BaseController
{
    private ContentPipelineOrchestrator $orchestrator;
    private ContentJobModel $contentJobModel;
    private GeneratedArticleModel $generatedArticleModel;
    private GeneratedImageModel $generatedImageModel;
    private WordpressSubmissionModel $wordpressSubmissionModel;
    private WordpressTaxonomyModel $wordpressTaxonomyModel;

    public function __construct()
    {
        $this->orchestrator = service('contentPipelineOrchestrator');
        $this->contentJobModel = new ContentJobModel();
        $this->generatedArticleModel = new GeneratedArticleModel();
        $this->generatedImageModel = new GeneratedImageModel();
        $this->wordpressSubmissionModel = new WordpressSubmissionModel();
        $this->wordpressTaxonomyModel = new WordpressTaxonomyModel();
    }

    public function index()
    {
        $latestJobs = $this->contentJobModel->orderBy('created_at', 'DESC')->findAll(10);

        $data = [
            'title' => 'Content Generator',
            'page_title' => 'Generate Konten Dari Prompt',
            'latestJobs' => $latestJobs,
            'ollamaDefaultModel' => config('ContentPipeline')->ollamaModel,
        ];

        return $this->renderView('content/index', $data);
    }

    public function generate()
    {
        $this->extendExecutionTime(300);

        $rules = [
            'prompt_text' => 'required|min_length[20]|max_length[5000]',
            'article_style' => 'required|in_list[informative,tutorial,listicle,storytelling]',
            'word_target' => 'required|in_list[800-1200,1200-1800,1800-2500]',
            'tone' => 'required|in_list[santai-edukatif,profesional-formal,persuasive-marketing]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $allowedProviders = ['openai', 'ollama'];
        $aiProvider = $this->request->getPost('ai_provider');
        $aiProvider = (is_string($aiProvider) && in_array($aiProvider, $allowedProviders, true)) ? $aiProvider : 'openai';

        // Sanitize ollama_model: only allow alphanumeric, dash, colon, dot (e.g. llama3, llama3:8b)
        $ollamaModel = (string) ($this->request->getPost('ollama_model') ?? '');
        $ollamaModel = preg_replace('/[^a-zA-Z0-9:\-\.]+/', '', $ollamaModel);

        $promptText = trim((string) $this->request->getPost('prompt_text'));
        $options = [
            'article_style' => (string) $this->request->getPost('article_style'),
            'word_target' => (string) $this->request->getPost('word_target'),
            'tone' => (string) $this->request->getPost('tone'),
            'ai_provider' => $aiProvider,
            'ollama_model' => $ollamaModel,
        ];

        $result = $this->orchestrator->runPromptGeneration($promptText, auth()->id(), $options);

        if (! ($result['success'] ?? false)) {
            return redirect()->to('/admin/content/history')
                ->with('error', ($result['error_message'] ?? 'Gagal generate konten.') . ' (Job ID: ' . ($result['job_id'] ?? '-') . ')');
        }

        return redirect()->to('/admin/content/detail/' . $result['job_id'])
            ->with('success', 'Job berhasil diproses. Silakan review hasil kontennya.');
    }

    public function generateStream()
    {
        $this->extendExecutionTime(300);

        $this->response->setHeader('Content-Type', 'text/event-stream');
        $this->response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->response->setHeader('Pragma', 'no-cache');
        $this->response->setHeader('X-Accel-Buffering', 'no');

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        $send = function (string $event, array $data = []): void {
            if (connection_aborted()) {
                throw new RuntimeException('CLIENT_DISCONNECTED');
            }

            echo 'event: ' . $event . "\n";
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();
        };

        $send('progress', ['message' => 'Memvalidasi input...']);

        $rules = [
            'prompt_text' => 'required|min_length[20]|max_length[5000]',
            'article_style' => 'required|in_list[informative,tutorial,listicle,storytelling]',
            'word_target' => 'required|in_list[800-1200,1200-1800,1800-2500]',
            'tone' => 'required|in_list[santai-edukatif,profesional-formal,persuasive-marketing]',
        ];

        if (! $this->validate($rules)) {
            $send('error', [
                'message' => 'Input tidak valid.',
                'errors' => $this->validator->getErrors(),
            ]);
            $send('done', ['success' => false]);

            return;
        }

        $allowedProviders = ['openai', 'ollama'];
        $aiProvider = $this->request->getPost('ai_provider');
        $aiProvider = (is_string($aiProvider) && in_array($aiProvider, $allowedProviders, true)) ? $aiProvider : 'openai';

        $ollamaModel = (string) ($this->request->getPost('ollama_model') ?? '');
        $ollamaModel = preg_replace('/[^a-zA-Z0-9:\-\.]+/', '', $ollamaModel);

        $promptText = trim((string) $this->request->getPost('prompt_text'));
        $options = [
            'article_style' => (string) $this->request->getPost('article_style'),
            'word_target' => (string) $this->request->getPost('word_target'),
            'tone' => (string) $this->request->getPost('tone'),
            'ai_provider' => $aiProvider,
            'ollama_model' => $ollamaModel,
        ];

        if ($aiProvider === 'ollama') {
            $options['on_chunk'] = static function (string $chunk) use ($send): bool {
                if (connection_aborted()) {
                    return false;
                }

                $send('content_chunk', [
                    'chunk' => $chunk,
                ]);

                return true;
            };
        }

        $send('progress', ['message' => 'Memulai proses generate konten...']);

        try {
            $result = $this->orchestrator->runPromptGeneration(
                $promptText,
                auth()->id(),
                $options,
                static function (string $stage, string $message, array $meta = []) use ($send): void {
                    $send('progress', [
                        'stage' => $stage,
                        'message' => $message,
                        'meta' => $meta,
                    ]);
                }
            );
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'CLIENT_DISCONNECTED') {
                return;
            }

            throw $e;
        }

        if (! ($result['success'] ?? false)) {
            $send('error', [
                'message' => (string) ($result['error_message'] ?? 'Gagal generate konten.'),
                'job_id' => (string) ($result['job_id'] ?? ''),
            ]);
            $send('done', ['success' => false]);

            return;
        }

        $jobId = (string) ($result['job_id'] ?? '');
        $send('complete', [
            'success' => true,
            'job_id' => $jobId,
            'redirect_url' => base_url('admin/content/detail/' . $jobId),
            'message' => 'Generate selesai. Mengarahkan ke halaman detail...',
        ]);
        $send('done', ['success' => true]);
    }

    public function history()
    {
        $jobs = $this->contentJobModel->orderBy('created_at', 'DESC')->findAll(100);

        $data = [
            'title' => 'Riwayat Content Job',
            'page_title' => 'Riwayat Generate Konten',
            'jobs' => $jobs,
        ];

        return $this->renderView('content/history', $data);
    }

    public function detail(string $jobId)
    {
        $job = $this->contentJobModel->find($jobId);
        if (! $job) {
            return redirect()->to('/admin/content/history')->with('error', 'Job tidak ditemukan.');
        }

        $article = $this->generatedArticleModel->where('content_job_id', $jobId)->first();
        $images = $this->generatedImageModel->where('content_job_id', $jobId)->findAll();
        $submissions = $this->wordpressSubmissionModel->where('content_job_id', $jobId)->orderBy('submitted_at', 'DESC')->findAll();
        $categories = $this->wordpressTaxonomyModel->where('taxonomy', 'category')->orderBy('name', 'ASC')->findAll();
        $tags = $this->wordpressTaxonomyModel->where('taxonomy', 'tag')->orderBy('name', 'ASC')->findAll();

        $data = [
            'title' => 'Detail Job Konten',
            'page_title' => 'Detail Job Konten',
            'job' => $job,
            'article' => $article,
            'images' => $images,
            'submissions' => $submissions,
            'categories' => $categories,
            'tags' => $tags,
        ];

        return $this->renderView('content/detail', $data);
    }

    public function publish(string $jobId)
    {
        $this->extendExecutionTime(300);

        $job = $this->contentJobModel->find($jobId);
        if (! $job) {
            return redirect()->to('/admin/content/history')->with('error', 'Job tidak ditemukan.');
        }

        $article = $this->generatedArticleModel->where('content_job_id', $jobId)->first();
        if (! $article) {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', 'Artikel belum tersedia untuk dipublish.');
        }

        $categories = $this->parseSelectedIds($this->request->getPost('wp_category_ids'));
        $tags = $this->parseSelectedIds($this->request->getPost('wp_tag_ids'));

        $content = (string) ($article['article_body_html'] ?? '');
        if (trim($content) === '') {
            $markdown = (string) ($article['article_body_markdown'] ?? '');
            $content = '<pre>' . esc($markdown) . '</pre>';
        }

        $selectedImage = $this->generatedImageModel
            ->where('content_job_id', $jobId)
            ->where('selected', 1)
            ->first();

        if (! is_array($selectedImage)) {
            $selectedImage = $this->generatedImageModel
                ->where('content_job_id', $jobId)
                ->first();
        }

        $wpMediaId = null;
        if (is_array($selectedImage)) {
            $publisher = service('wordPressPublisher');
            $mediaResult = $publisher->uploadFeaturedMedia([
                'local_path' => (string) ($selectedImage['local_path'] ?? ''),
                'source_url' => (string) ($selectedImage['source_url'] ?? ''),
            ]);

            if (! ($mediaResult['success'] ?? false)) {
                return redirect()->to('/admin/content/detail/' . $jobId)->with('error', 'Gagal upload featured image ke WordPress: ' . (string) ($mediaResult['error_message'] ?? '-'));
            }

            $mediaData = is_array($mediaResult['data'] ?? null) ? $mediaResult['data'] : [];
            $wpMediaId = isset($mediaData['wp_media_id']) ? (int) $mediaData['wp_media_id'] : null;
        }

        $allowedStatuses = ['draft', 'publish'];
        $postStatus = $this->request->getPost('post_status');
        $postStatus = (is_string($postStatus) && in_array($postStatus, $allowedStatuses, true)) ? $postStatus : 'draft';

        $payload = [
            'title' => (string) ($article['seo_title'] ?: $article['article_title'] ?? 'Untitled Draft'),
            'content' => $content,
            'categories' => $categories,
            'tags' => $tags,
            'status' => $postStatus,
        ];

        if ($wpMediaId !== null) {
            $payload['featured_media_id'] = $wpMediaId;
        }

        $publisher = service('wordPressPublisher');
        $result = $publisher->createDraft($payload);

        if (! ($result['success'] ?? false)) {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', (string) ($result['error_message'] ?? 'Gagal publish draft ke WordPress.'));
        }

        $config = config('ContentPipeline');
        $host = (string) (parse_url((string) $config->wordpressBaseUrl, PHP_URL_HOST) ?: 'default');
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        $this->wordpressSubmissionModel->insert([
            'id' => $this->uuidV4(),
            'content_job_id' => $jobId,
            'wp_site_key' => $host,
            'wp_post_id' => isset($data['wp_post_id']) ? (int) $data['wp_post_id'] : null,
            'wp_post_url' => (string) ($data['wp_post_url'] ?? ''),
            'wp_media_id' => $wpMediaId,
            'wp_status' => (string) ($data['wp_status'] ?? 'draft'),
            'wp_category_ids_json' => json_encode($categories, JSON_UNESCAPED_UNICODE),
            'wp_tag_ids_json' => json_encode($tags, JSON_UNESCAPED_UNICODE),
            'request_snapshot_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'response_snapshot_json' => json_encode($data['raw'] ?? $data, JSON_UNESCAPED_UNICODE),
            'submitted_by' => auth()->id(),
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        $this->contentJobModel->update($jobId, [
            'status' => 'submitted',
            'error_code' => null,
            'error_message' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $statusLabel = $postStatus === 'publish' ? 'dipublikasikan' : 'disimpan sebagai draft';
        return redirect()->to('/admin/content/detail/' . $jobId)->with('success', 'Berhasil! Post WordPress ' . $statusLabel . '. Post ID: ' . (string) ($data['wp_post_id'] ?? '-'));
    }

    public function checkOllama()
    {
        $config = config('ContentPipeline');
        $baseUrl = rtrim((string) $config->ollamaBaseUrl, '/');
        $model = (string) $config->ollamaModel;

        try {
            $client = \Config\Services::curlrequest();
            $response = $client->get($baseUrl . '/api/tags', [
                'timeout' => 5,
                'headers' => ['Accept' => 'application/json'],
            ]);

            $ok = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
            $decoded = json_decode($response->getBody(), true);
            $availableModels = [];
            if (is_array($decoded['models'] ?? null)) {
                foreach ($decoded['models'] as $m) {
                    if (isset($m['name'])) {
                        $availableModels[] = (string) $m['name'];
                    }
                }
            }
            $modelFound = in_array($model, $availableModels, true)
                || count(array_filter($availableModels, fn ($n) => str_starts_with($n, $model))) > 0;

            return $this->response->setJSON([
                'ok' => $ok,
                'base_url' => $baseUrl,
                'configured_model' => $model,
                'available_models' => $availableModels,
                'model_found' => $modelFound,
                'message' => $ok
                    ? ($modelFound ? "Ollama aktif. Model '{$model}' tersedia." : "Ollama aktif, tetapi model '{$model}' tidak ditemukan. Model tersedia: " . implode(', ', $availableModels))
                    : 'Ollama merespons dengan status ' . $response->getStatusCode() . '.',
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'ok' => false,
                'base_url' => $baseUrl,
                'configured_model' => $model,
                'available_models' => [],
                'model_found' => false,
                'message' => 'Tidak dapat terhubung ke Ollama (' . $baseUrl . '): ' . $e->getMessage(),
            ]);
        }
    }

    public function regenerateText(string $jobId)
    {
        $this->extendExecutionTime(300);

        $job = $this->contentJobModel->find($jobId);
        if (! is_array($job)) {
            return redirect()->to('/admin/content/history')->with('error', 'Job tidak ditemukan.');
        }

        $result = $this->orchestrator->regenerateArticle($jobId);
        if (! ($result['success'] ?? false)) {
            return redirect()->to('/admin/content/detail/' . $jobId)
                ->with('error', (string) ($result['error_message'] ?? 'Gagal regenerate artikel.'));
        }

        return redirect()->to('/admin/content/detail/' . $jobId)
            ->with('success', 'Artikel berhasil diregenerate pada job yang sama. Silakan review ulang hasilnya.');
    }

    public function generateImage(string $jobId)
    {
        $this->extendExecutionTime(300);

        $job = $this->contentJobModel->find($jobId);
        if (! $job) {
            return redirect()->to('/admin/content/history')->with('error', 'Job tidak ditemukan.');
        }

        $article = $this->generatedArticleModel->where('content_job_id', $jobId)->first();
        $query = (string) ($article['primary_keyword'] ?? $article['article_title'] ?? $job['prompt_text'] ?? 'blog featured image');

        $imageProvider = service('featuredImageProvider');
        $result = $imageProvider->search($query, 1);

        if (! ($result['success'] ?? false) || empty($result['images'])) {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', 'Gagal generate gambar AI: ' . (string) ($result['error_message'] ?? '-'));
        }

        $image = $result['images'][0];
        // Deselect previous images
        $this->generatedImageModel->where('content_job_id', $jobId)->set(['selected' => 0])->update();
        $this->generatedImageModel->insert([
            'id' => $this->uuidV4(),
            'content_job_id' => $jobId,
            'provider_name' => (string) ($image['provider'] ?? 'openai'),
            'source_url' => (string) ($image['source_url'] ?? ''),
            'local_path' => $image['local_path'] ?? null,
            'credit_text' => $image['credit_text'] ?? null,
            'license_info' => $image['license_info'] ?? null,
            'width' => isset($image['width']) ? (int) $image['width'] : null,
            'height' => isset($image['height']) ? (int) $image['height'] : null,
            'selected' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/admin/content/detail/' . $jobId)->with('success', 'Featured image AI berhasil digenerate.');
    }

    public function uploadImage(string $jobId)
    {
        $job = $this->contentJobModel->find($jobId);
        if (! $job) {
            return redirect()->to('/admin/content/history')->with('error', 'Job tidak ditemukan.');
        }

        $uploadedFile = $this->request->getFile('featured_image');
        if (! $uploadedFile || ! $uploadedFile->isValid() || $uploadedFile->hasMoved()) {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', 'File gambar tidak valid atau tidak terupload.');
        }

        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (! in_array($uploadedFile->getMimeType(), $allowedMime, true)) {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', 'Format file tidak didukung. Gunakan JPG, PNG, WEBP, atau GIF.');
        }

        $maxSizeKb = 5120; // 5 MB
        if ($uploadedFile->getSizeByUnit('kb') > $maxSizeKb) {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', 'Ukuran file melebihi batas 5MB.');
        }

        $destDir = FCPATH . 'uploads/generated/';
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $newName = 'manual_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $uploadedFile->getClientExtension();
        $uploadedFile->move($destDir, $newName);

        $localPath = 'uploads/generated/' . $newName;
        $sourceUrl = base_url($localPath);

        // Deselect previous images
        $this->generatedImageModel->where('content_job_id', $jobId)->set(['selected' => 0])->update();
        $this->generatedImageModel->insert([
            'id' => $this->uuidV4(),
            'content_job_id' => $jobId,
            'provider_name' => 'manual_upload',
            'source_url' => $sourceUrl,
            'local_path' => $localPath,
            'credit_text' => 'Manual upload',
            'license_info' => null,
            'width' => null,
            'height' => null,
            'selected' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/admin/content/detail/' . $jobId)->with('success', 'Featured image berhasil diupload.');
    }

    /**
     * @return list<int>
     */
    private function parseCsvIds(string $csv): array
    {
        $parts = array_filter(array_map('trim', explode(',', $csv)), static fn ($v) => $v !== '');

        $ids = [];
        foreach ($parts as $part) {
            if (ctype_digit($part)) {
                $ids[] = (int) $part;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param mixed $values
     * @return list<int>
     */
    private function parseSelectedIds(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $ids = [];
        foreach ($values as $value) {
            $candidate = (string) $value;
            if (ctype_digit($candidate)) {
                $ids[] = (int) $candidate;
            }
        }

        return array_values(array_unique($ids));
    }

    public function syncTaxonomies(string $jobId)
    {
        $publisher = service('wordPressPublisher');
        $categoriesResult = $publisher->fetchCategories();
        $tagsResult = $publisher->fetchTags();

        if (! ($categoriesResult['success'] ?? false)) {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', (string) ($categoriesResult['error_message'] ?? 'Gagal sync category WordPress.'));
        }

        if (! ($tagsResult['success'] ?? false)) {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', (string) ($tagsResult['error_message'] ?? 'Gagal sync tag WordPress.'));
        }

        $this->wordpressTaxonomyModel->where('taxonomy', 'category')->delete();
        $this->wordpressTaxonomyModel->where('taxonomy', 'tag')->delete();

        $now = date('Y-m-d H:i:s');
        foreach ((array) ($categoriesResult['data'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['name'])) {
                continue;
            }

            $this->wordpressTaxonomyModel->insert([
                'taxonomy' => 'category',
                'wp_term_id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) ($row['slug'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'post_count' => (int) ($row['count'] ?? 0),
                'synced_at' => $now,
            ]);
        }

        foreach ((array) ($tagsResult['data'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['name'])) {
                continue;
            }

            $this->wordpressTaxonomyModel->insert([
                'taxonomy' => 'tag',
                'wp_term_id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) ($row['slug'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'post_count' => (int) ($row['count'] ?? 0),
                'synced_at' => $now,
            ]);
        }

        return redirect()->to('/admin/content/detail/' . $jobId)->with('success', 'Kategori dan tag berhasil disinkronkan dari WordPress.');
    }

    public function createCategory(string $jobId)
    {
        $name = trim((string) $this->request->getPost('name'));
        $slug = trim((string) $this->request->getPost('slug'));

        if ($name === '') {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', 'Nama kategori wajib diisi.');
        }

        $publisher = service('wordPressPublisher');
        $result = $publisher->createCategory(['name' => $name, 'slug' => $slug]);

        if (! ($result['success'] ?? false)) {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', (string) ($result['error_message'] ?? 'Gagal membuat kategori di WordPress.'));
        }

        $this->syncSingleTaxonomy('category', (array) ($result['data'] ?? []));

        return redirect()->to('/admin/content/detail/' . $jobId)->with('success', 'Kategori baru berhasil ditambahkan ke WordPress.');
    }

    public function createTag(string $jobId)
    {
        $name = trim((string) $this->request->getPost('name'));
        $slug = trim((string) $this->request->getPost('slug'));

        if ($name === '') {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', 'Nama tag wajib diisi.');
        }

        $publisher = service('wordPressPublisher');
        $result = $publisher->createTag(['name' => $name, 'slug' => $slug]);

        if (! ($result['success'] ?? false)) {
            return redirect()->to('/admin/content/detail/' . $jobId)->with('error', (string) ($result['error_message'] ?? 'Gagal membuat tag di WordPress.'));
        }

        $this->syncSingleTaxonomy('tag', (array) ($result['data'] ?? []));

        return redirect()->to('/admin/content/detail/' . $jobId)->with('success', 'Tag baru berhasil ditambahkan ke WordPress.');
    }

    private function syncSingleTaxonomy(string $taxonomy, array $row): void
    {
        if (! isset($row['id'], $row['name'])) {
            return;
        }

        $existing = $this->wordpressTaxonomyModel
            ->where('taxonomy', $taxonomy)
            ->where('wp_term_id', (int) $row['id'])
            ->first();

        $data = [
            'taxonomy' => $taxonomy,
            'wp_term_id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) ($row['slug'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'post_count' => (int) ($row['count'] ?? 0),
            'synced_at' => date('Y-m-d H:i:s'),
        ];

        if (is_array($existing) && isset($existing['id'])) {
            $this->wordpressTaxonomyModel->update((int) $existing['id'], $data);
        } else {
            $this->wordpressTaxonomyModel->insert($data);
        }
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function extendExecutionTime(int $seconds): void
    {
        if ($seconds < 1) {
            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }

        @ini_set('max_execution_time', (string) $seconds);
    }
}
