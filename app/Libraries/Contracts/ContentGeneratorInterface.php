<?php

namespace App\Libraries\Contracts;

interface ContentGeneratorInterface
{
    /**
     * Return shape:
     * - success: bool
     * - article: ?array
     * - error_code: ?string
     * - error_message: ?string
     */
    public function generate(array $payload): array;
}
