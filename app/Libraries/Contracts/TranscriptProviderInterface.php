<?php

namespace App\Libraries\Contracts;

interface TranscriptProviderInterface
{
    /**
     * Return shape:
     * - success: bool
     * - transcript: ?string
     * - source_data: array{title?:string,description?:string,comments?:array}
     * - error_code: ?string
     * - error_message: ?string
     */
    public function extractFromYoutubeUrl(string $youtubeUrl): array;
}
