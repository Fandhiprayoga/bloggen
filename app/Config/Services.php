<?php

namespace Config;

use App\Libraries\Contracts\ContentGeneratorInterface;
use App\Libraries\Contracts\FeaturedImageProviderInterface;
use App\Libraries\Contracts\TranscriptProviderInterface;
use App\Libraries\Contracts\WordPressPublisherInterface;
use App\Libraries\Services\OllamaContentGeneratorService;
use App\Libraries\Services\OpenAIContentGeneratorService;
use App\Libraries\Services\ContentPipelineOrchestrator;
use App\Libraries\Services\StockImageService;
use App\Libraries\Services\WordPressPublisherService;
use App\Libraries\Services\YoutubeTranscriptService;
use App\Models\ContentJobModel;
use App\Models\GeneratedArticleModel;
use App\Models\GeneratedImageModel;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function transcriptProvider(bool $getShared = true): TranscriptProviderInterface
    {
        if ($getShared) {
            return static::getSharedInstance('transcriptProvider');
        }

        return new YoutubeTranscriptService(
            config('ContentPipeline'),
            static::curlrequest(),
            static::logger(),
        );
    }

    public static function contentGenerator(bool $getShared = true): ContentGeneratorInterface
    {
        if ($getShared) {
            return static::getSharedInstance('contentGenerator');
        }

        return new OpenAIContentGeneratorService(
            config('ContentPipeline'),
            static::curlrequest(),
            static::logger(),
        );
    }

    public static function featuredImageProvider(bool $getShared = true): FeaturedImageProviderInterface
    {
        if ($getShared) {
            return static::getSharedInstance('featuredImageProvider');
        }

        return new StockImageService(
            config('ContentPipeline'),
            static::curlrequest(),
            static::logger(),
        );
    }

    public static function wordPressPublisher(bool $getShared = true): WordPressPublisherInterface
    {
        if ($getShared) {
            return static::getSharedInstance('wordPressPublisher');
        }

        return new WordPressPublisherService(
            config('ContentPipeline'),
            static::curlrequest(),
            static::logger(),
        );
    }

    public static function contentPipelineOrchestrator(bool $getShared = true): ContentPipelineOrchestrator
    {
        if ($getShared) {
            return static::getSharedInstance('contentPipelineOrchestrator');
        }

        return new ContentPipelineOrchestrator(
            static::contentGenerator(),
            static::featuredImageProvider(),
            new ContentJobModel(),
            new GeneratedArticleModel(),
            new GeneratedImageModel(),
            static::logger(),
        );
    }
}
