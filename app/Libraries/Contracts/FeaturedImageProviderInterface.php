<?php

namespace App\Libraries\Contracts;

interface FeaturedImageProviderInterface
{
    /**
     * Return shape:
     * - success: bool
     * - images: array<int,array<string,mixed>>
     * - error_code: ?string
     * - error_message: ?string
     */
    public function search(string $query, int $limit = 5, ?string $format = null): array;
}
