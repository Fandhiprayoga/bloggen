<?php

namespace App\Libraries\Services;

use App\Libraries\Contracts\WordPressPublisherInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\ContentPipeline;
use Psr\Log\LoggerInterface;
use Throwable;

class WordPressPublisherService implements WordPressPublisherInterface
{
    public function __construct(
        private readonly ContentPipeline $config,
        private readonly CURLRequest $http,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createDraft(array $payload): array
    {
        if ($this->config->wordpressBaseUrl === '' || $this->config->wordpressUsername === '' || $this->config->wordpressAppPassword === '') {
            return [
                'success' => false,
                'data' => null,
                'error_code' => 'WP_CREDENTIALS_MISSING',
                'error_message' => 'Kredensial WordPress belum lengkap di env.',
            ];
        }

        if (empty($payload['title']) || empty($payload['content'])) {
            return [
                'success' => false,
                'data' => null,
                'error_code' => 'INVALID_PAYLOAD',
                'error_message' => 'Payload title dan content wajib tersedia.',
            ];
        }

        try {
            $endpoint = rtrim($this->config->wordpressBaseUrl, '/') . '/wp-json/wp/v2/posts';

            $allowedStatuses = ['draft', 'publish', 'pending', 'private'];
            $postStatus = isset($payload['status']) && in_array($payload['status'], $allowedStatuses, true)
                ? $payload['status']
                : 'draft';

            $postPayload = [
                'title' => (string) $payload['title'],
                'content' => (string) $payload['content'],
                'status' => $postStatus,
            ];

            if (is_array($payload['categories'] ?? null)) {
                $postPayload['categories'] = array_values(array_map('intval', $payload['categories']));
            }

            if (is_array($payload['tags'] ?? null)) {
                $postPayload['tags'] = array_values(array_map('intval', $payload['tags']));
            }

            if (isset($payload['featured_media_id'])) {
                $postPayload['featured_media'] = (int) $payload['featured_media_id'];
            }

            $response = $this->http->post($endpoint, [
                'timeout' => $this->config->requestTimeout,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->config->wordpressUsername . ':' . $this->config->wordpressAppPassword),
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($postPayload, JSON_UNESCAPED_UNICODE),
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return [
                    'success' => false,
                    'data' => null,
                    'error_code' => 'WP_POST_CREATE_HTTP_ERROR',
                    'error_message' => 'Gagal membuat draft WordPress. Status ' . $response->getStatusCode() . '.',
                ];
            }

            $decoded = json_decode($response->getBody(), true);
            if (! is_array($decoded) || ! isset($decoded['id'])) {
                return [
                    'success' => false,
                    'data' => null,
                    'error_code' => 'WP_INVALID_RESPONSE',
                    'error_message' => 'Response WordPress tidak valid.',
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'wp_post_id' => (int) $decoded['id'],
                    'wp_post_url' => (string) ($decoded['link'] ?? ''),
                    'wp_status' => (string) ($decoded['status'] ?? 'draft'),
                    'raw' => $decoded,
                ],
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            $this->logger->error('WordPress publish failed.', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'error_code' => 'WP_REQUEST_EXCEPTION',
                'error_message' => 'Terjadi error saat publish draft ke WordPress.',
            ];
        }
    }

    public function uploadFeaturedMedia(array $payload): array
    {
        if ($this->config->wordpressBaseUrl === '' || $this->config->wordpressUsername === '' || $this->config->wordpressAppPassword === '') {
            return [
                'success' => false,
                'data' => null,
                'error_code' => 'WP_CREDENTIALS_MISSING',
                'error_message' => 'Kredensial WordPress belum lengkap di env.',
            ];
        }

        $image = $this->resolveImageBinary($payload);
        if (! ($image['success'] ?? false)) {
            return $image;
        }

        $binary = (string) ($image['binary'] ?? '');
        $filename = (string) ($image['filename'] ?? 'featured-image.png');
        $mime = (string) ($image['mime'] ?? 'image/png');

        try {
            $endpoint = rtrim($this->config->wordpressBaseUrl, '/') . '/wp-json/wp/v2/media';

            $response = $this->http->post($endpoint, [
                'timeout' => $this->config->requestTimeout,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->config->wordpressUsername . ':' . $this->config->wordpressAppPassword),
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
                ],
                'body' => $binary,
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return [
                    'success' => false,
                    'data' => null,
                    'error_code' => 'WP_MEDIA_UPLOAD_HTTP_ERROR',
                    'error_message' => 'Gagal upload media ke WordPress. Status ' . $response->getStatusCode() . '.',
                ];
            }

            $decoded = json_decode($response->getBody(), true);
            if (! is_array($decoded) || ! isset($decoded['id'])) {
                return [
                    'success' => false,
                    'data' => null,
                    'error_code' => 'WP_MEDIA_INVALID_RESPONSE',
                    'error_message' => 'Response media WordPress tidak valid.',
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'wp_media_id' => (int) $decoded['id'],
                    'wp_media_url' => (string) ($decoded['source_url'] ?? ''),
                    'raw' => $decoded,
                ],
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            $this->logger->error('WordPress media upload failed.', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'error_code' => 'WP_MEDIA_UPLOAD_EXCEPTION',
                'error_message' => 'Terjadi error saat upload media ke WordPress: ' . $e->getMessage(),
            ];
        }
    }

    public function fetchCategories(): array
    {
        return $this->fetchTaxonomy('categories');
    }

    public function fetchTags(): array
    {
        return $this->fetchTaxonomy('tags');
    }

    public function createCategory(array $payload): array
    {
        return $this->createTaxonomy('categories', $payload);
    }

    public function createTag(array $payload): array
    {
        return $this->createTaxonomy('tags', $payload);
    }

    private function resolveImageBinary(array $payload): array
    {
        $localPath = trim((string) ($payload['local_path'] ?? ''));
        $sourceUrl = trim((string) ($payload['source_url'] ?? ''));

        if ($localPath !== '') {
            $absolutePath = FCPATH . ltrim($localPath, '/');
            if (is_file($absolutePath) && is_readable($absolutePath)) {
                $binary = file_get_contents($absolutePath);
                if ($binary !== false && $binary !== '') {
                    return [
                        'success' => true,
                        'binary' => $binary,
                        'filename' => basename($absolutePath),
                        'mime' => $this->detectMime($binary, basename($absolutePath)),
                        'error_code' => null,
                        'error_message' => null,
                    ];
                }
            }
        }

        if ($sourceUrl !== '' && (str_starts_with($sourceUrl, 'http://') || str_starts_with($sourceUrl, 'https://'))) {
            try {
                $response = $this->http->get($sourceUrl, [
                    'timeout' => $this->config->requestTimeout,
                ]);

                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    $binary = $response->getBody();
                    if ($binary !== '') {
                        $filename = basename(parse_url($sourceUrl, PHP_URL_PATH) ?: 'featured-image.png');

                        return [
                            'success' => true,
                            'binary' => $binary,
                            'filename' => $filename,
                            'mime' => $this->detectMime($binary, $filename),
                            'error_code' => null,
                            'error_message' => null,
                        ];
                    }
                }
            } catch (Throwable $e) {
                $this->logger->warning('Failed to download source image URL for WordPress upload.', [
                    'source_url' => $sourceUrl,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => false,
            'data' => null,
            'error_code' => 'FEATURED_IMAGE_SOURCE_UNAVAILABLE',
            'error_message' => 'Sumber image tidak tersedia untuk upload media WordPress.',
        ];
    }

    private function detectMime(string $binary, string $filename): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);

        if (is_string($mime) && $mime !== '') {
            return $mime;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/png',
        };
    }

    private function fetchTaxonomy(string $taxonomy): array
    {
        if ($this->config->wordpressBaseUrl === '' || $this->config->wordpressUsername === '' || $this->config->wordpressAppPassword === '') {
            return [
                'success' => false,
                'data' => null,
                'error_code' => 'WP_CREDENTIALS_MISSING',
                'error_message' => 'Kredensial WordPress belum lengkap di env.',
            ];
        }

        try {
            $endpoint = rtrim($this->config->wordpressBaseUrl, '/') . '/wp-json/wp/v2/' . $taxonomy;

            $response = $this->http->get($endpoint, [
                'timeout' => $this->config->requestTimeout,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->config->wordpressUsername . ':' . $this->config->wordpressAppPassword),
                ],
                'query' => [
                    'per_page' => 100,
                    'orderby' => 'name',
                    'order' => 'asc',
                ],
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return [
                    'success' => false,
                    'data' => null,
                    'error_code' => 'WP_TAXONOMY_FETCH_HTTP_ERROR',
                    'error_message' => 'Gagal ambil data ' . $taxonomy . ' dari WordPress. Status ' . $response->getStatusCode() . '.',
                ];
            }

            $decoded = json_decode($response->getBody(), true);
            if (! is_array($decoded)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error_code' => 'WP_TAXONOMY_INVALID_RESPONSE',
                    'error_message' => 'Response taxonomy WordPress tidak valid.',
                ];
            }

            return [
                'success' => true,
                'data' => $decoded,
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'data' => null,
                'error_code' => 'WP_TAXONOMY_FETCH_EXCEPTION',
                'error_message' => 'Terjadi error saat sync ' . $taxonomy . ': ' . $e->getMessage(),
            ];
        }
    }

    private function createTaxonomy(string $taxonomy, array $payload): array
    {
        if ($this->config->wordpressBaseUrl === '' || $this->config->wordpressUsername === '' || $this->config->wordpressAppPassword === '') {
            return [
                'success' => false,
                'data' => null,
                'error_code' => 'WP_CREDENTIALS_MISSING',
                'error_message' => 'Kredensial WordPress belum lengkap di env.',
            ];
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return [
                'success' => false,
                'data' => null,
                'error_code' => 'INVALID_PAYLOAD',
                'error_message' => 'Nama taxonomy wajib diisi.',
            ];
        }

        try {
            $endpoint = rtrim($this->config->wordpressBaseUrl, '/') . '/wp-json/wp/v2/' . $taxonomy;

            $bodyPayload = [
                'name' => $name,
            ];

            if (! empty($payload['slug'])) {
                $bodyPayload['slug'] = (string) $payload['slug'];
            }

            $response = $this->http->post($endpoint, [
                'timeout' => $this->config->requestTimeout,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->config->wordpressUsername . ':' . $this->config->wordpressAppPassword),
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($bodyPayload, JSON_UNESCAPED_UNICODE),
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return [
                    'success' => false,
                    'data' => null,
                    'error_code' => 'WP_TAXONOMY_CREATE_HTTP_ERROR',
                    'error_message' => 'Gagal membuat ' . rtrim($taxonomy, 's') . ' di WordPress. Status ' . $response->getStatusCode() . '.',
                ];
            }

            $decoded = json_decode($response->getBody(), true);
            if (! is_array($decoded) || ! isset($decoded['id'])) {
                return [
                    'success' => false,
                    'data' => null,
                    'error_code' => 'WP_TAXONOMY_CREATE_INVALID_RESPONSE',
                    'error_message' => 'Response create taxonomy WordPress tidak valid.',
                ];
            }

            return [
                'success' => true,
                'data' => $decoded,
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'data' => null,
                'error_code' => 'WP_TAXONOMY_CREATE_EXCEPTION',
                'error_message' => 'Terjadi error saat membuat taxonomy: ' . $e->getMessage(),
            ];
        }
    }
}
