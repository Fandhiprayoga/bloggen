<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

class ContentPipelineHealth extends BaseCommand
{
    protected $group = 'Content';
    protected $name = 'content:health';
    protected $description = 'Health check untuk pipeline konten berbasis prompt (OpenAI text, feature image, WordPress).';
    protected $usage = 'content:health [--live]';
    protected $arguments = [];
    protected $options = [
        '--live' => 'Jalankan pengujian live ke API eksternal.',
    ];

    public function run(array $params)
    {
        $isLive = (bool) CLI::getOption('live');

        CLI::write('=== Content Pipeline Health Check ===', 'yellow');
        CLI::write('Mode: ' . ($isLive ? 'LIVE' : 'DRY-RUN'), 'cyan');
        CLI::newLine();

        $config = config('ContentPipeline');

        $this->checkConfig('OpenAI Key', $config->openAIKey !== '');
        if (strtolower($config->stockImageProvider) === 'unsplash') {
            $this->checkConfig('Stock Image Key', $config->stockImageApiKey !== '');
        } else {
            $this->checkConfig('Image Provider (OpenAI)', strtolower($config->stockImageProvider) === 'openai');
        }
        $this->checkConfig('WordPress Base URL', $config->wordpressBaseUrl !== '');
        $this->checkConfig('WordPress Username', $config->wordpressUsername !== '');
        $this->checkConfig('WordPress App Password', $config->wordpressAppPassword !== '');

        if (! $isLive) {
            CLI::newLine();
            CLI::write('Dry-run selesai. Jalankan dengan --live untuk uji API eksternal.', 'yellow');

            return;
        }

        CLI::newLine();
        CLI::write('--- Live Checks ---', 'yellow');

        $this->runOpenAICheck();
        $this->runStockImageCheck();
        $this->runWordPressCheck();

        CLI::newLine();
        CLI::write('Health check selesai.', 'green');
    }

    private function checkConfig(string $label, bool $ok): void
    {
        if ($ok) {
            CLI::write('[OK] ' . $label, 'green');

            return;
        }

        CLI::write('[MISS] ' . $label, 'red');
    }

    private function runOpenAICheck(): void
    {
        CLI::write('OpenAI generator check...', 'cyan');

        $generator = Services::contentGenerator(false);
        $result = $generator->generate([
            'prompt_text' => 'Buat artikel SEO tentang strategi promosi digital untuk UMKM Indonesia.',
            'title' => null,
            'description' => null,
            'comments' => [],
            'language' => 'id',
            'tone' => 'santai-edukatif',
            'word_target' => '1200-1800',
        ]);

        if (($result['success'] ?? false) === true) {
            $title = (string) (($result['article']['title'] ?? '')); // @phpstan-ignore-line
            CLI::write('[OK] OpenAI response valid. Title: ' . $title, 'green');

            return;
        }

        CLI::write('[FAIL] OpenAI: ' . (string) ($result['error_code'] ?? 'UNKNOWN'), 'red');
        CLI::write('       Detail: ' . (string) ($result['error_message'] ?? '-'), 'yellow');
    }

    private function runStockImageCheck(): void
    {
        CLI::write('Stock image provider check...', 'cyan');

        $provider = Services::featuredImageProvider(false);
        $result = $provider->search('teknologi digital', 3);

        if (($result['success'] ?? false) === true) {
            $count = count((array) ($result['images'] ?? []));
            CLI::write('[OK] Stock image results: ' . $count, 'green');

            return;
        }

        CLI::write('[FAIL] Stock image: ' . (string) ($result['error_code'] ?? 'UNKNOWN'), 'red');
        CLI::write('       Detail: ' . (string) ($result['error_message'] ?? '-'), 'yellow');
    }

    private function runWordPressCheck(): void
    {
        CLI::write('WordPress draft publish check...', 'cyan');

        $publisher = Services::wordPressPublisher(false);
        $result = $publisher->createDraft([
            'title' => 'Draft Uji Integrasi',
            'content' => '<p>Konten uji dari health-check command.</p>',
            'categories' => [],
            'tags' => [],
        ]);

        if (($result['success'] ?? false) === true) {
            $postId = (string) (($result['data']['wp_post_id'] ?? '')); // @phpstan-ignore-line
            CLI::write('[OK] WordPress draft created. Post ID: ' . $postId, 'green');

            return;
        }

        CLI::write('[FAIL] WordPress: ' . (string) ($result['error_code'] ?? 'UNKNOWN'), 'red');
        CLI::write('       Detail: ' . (string) ($result['error_message'] ?? '-'), 'yellow');
    }
}
