<?php

namespace App\Libraries\Contracts;

interface WordPressPublisherInterface
{
    /**
     * Return shape:
     * - success: bool
     * - data: ?array
     * - error_code: ?string
     * - error_message: ?string
     */
    public function createDraft(array $payload): array;

    /**
     * Return shape:
     * - success: bool
     * - data: ?array
     * - error_code: ?string
     * - error_message: ?string
     */
    public function uploadFeaturedMedia(array $payload): array;

    /**
     * Return shape:
     * - success: bool
     * - data: list<array<string,mixed>>|null
     * - error_code: ?string
     * - error_message: ?string
     */
    public function fetchCategories(): array;

    /**
     * Return shape:
     * - success: bool
     * - data: list<array<string,mixed>>|null
     * - error_code: ?string
     * - error_message: ?string
     */
    public function fetchTags(): array;

    /**
     * Return shape:
     * - success: bool
     * - data: ?array
     * - error_code: ?string
     * - error_message: ?string
     */
    public function createCategory(array $payload): array;

    /**
     * Return shape:
     * - success: bool
     * - data: ?array
     * - error_code: ?string
     * - error_message: ?string
     */
    public function createTag(array $payload): array;

    /**
     * Check connectivity to WordPress REST API.
     *
     * Return shape:
     * - success: bool
     * - data: ?array
     * - error_code: ?string
     * - error_message: ?string
     */
    public function checkConnection(): array;
}
