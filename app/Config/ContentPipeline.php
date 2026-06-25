<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class ContentPipeline extends BaseConfig
{
    public string $openAIKey = '';
    public string $openAIModel = 'gpt-4o-mini';
    public string $openAIImageModel = 'gpt-image-1';
    public string $openAIImageFormat = 'webp';
    public string $openAIBaseUrl = 'https://api.openai.com/v1';

    public string $ollamaBaseUrl = 'http://localhost:11434';
    public string $ollamaModel = 'llama3';

    public string $youtubeApiKey = '';

    public string $stockImageProvider = 'openai';
    public string $stockImageApiKey = '';
    public string $stockImageBaseUrl = 'https://api.unsplash.com';

    public string $wordpressBaseUrl = '';
    public string $wordpressUsername = '';
    public string $wordpressAppPassword = '';

    public int $requestTimeout = 30;
    public int $maxRetry = 2;

    public function __construct()
    {
        parent::__construct();

        $this->openAIKey = (string) env('content.openAIKey', $this->openAIKey);
        $this->openAIModel = (string) env('content.openAIModel', $this->openAIModel);
        $this->openAIImageModel = (string) env('content.openAIImageModel', $this->openAIImageModel);
        $this->openAIImageFormat = (string) env('content.openAIImageFormat', $this->openAIImageFormat);
        $this->openAIBaseUrl = (string) env('content.openAIBaseUrl', $this->openAIBaseUrl);

        $this->ollamaBaseUrl = (string) env('content.ollamaBaseUrl', $this->ollamaBaseUrl);
        $this->ollamaModel = (string) env('content.ollamaModel', $this->ollamaModel);

        $this->youtubeApiKey = (string) env('content.youtubeApiKey', $this->youtubeApiKey);

        $this->stockImageProvider = (string) env('content.stockImageProvider', $this->stockImageProvider);
        $this->stockImageApiKey = (string) env('content.stockImageApiKey', $this->stockImageApiKey);
        $this->stockImageBaseUrl = (string) env('content.stockImageBaseUrl', $this->stockImageBaseUrl);

        $this->wordpressBaseUrl = rtrim((string) env('content.wordpressBaseUrl', $this->wordpressBaseUrl), '/');
        $this->wordpressUsername = (string) env('content.wordpressUsername', $this->wordpressUsername);
        $this->wordpressAppPassword = (string) env('content.wordpressAppPassword', $this->wordpressAppPassword);

        $this->requestTimeout = (int) env('content.requestTimeout', $this->requestTimeout);
        $this->maxRetry = (int) env('content.maxRetry', $this->maxRetry);
    }
}
