<?php

namespace App\Libraries\Services;

use App\Libraries\Contracts\FeaturedImageProviderInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\ContentPipeline;
use Psr\Log\LoggerInterface;
use Throwable;

class StockImageService implements FeaturedImageProviderInterface
{
    public function __construct(
        private readonly ContentPipeline $config,
        private readonly CURLRequest $http,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function search(string $query, int $limit = 5): array
    {
        if (trim($query) === '') {
            return [
                'success' => false,
                'images' => [],
                'error_code' => 'QUERY_REQUIRED',
                'error_message' => 'Kata kunci image wajib diisi.',
            ];
        }

        $provider = strtolower($this->config->stockImageProvider);

        if ($provider === 'openai') {
            return $this->searchWithOpenAI($query);
        }

        if ($this->config->stockImageApiKey === '') {
            return [
                'success' => false,
                'images' => [],
                'error_code' => 'STOCK_IMAGE_KEY_MISSING',
                'error_message' => 'Stock image API key belum dikonfigurasi di env.',
            ];
        }

        if ($provider !== 'unsplash') {
            return [
                'success' => false,
                'images' => [],
                'error_code' => 'UNSUPPORTED_IMAGE_PROVIDER',
                'error_message' => 'Provider image belum didukung. Gunakan openai atau unsplash.',
            ];
        }

        try {
            $response = $this->http->get(rtrim($this->config->stockImageBaseUrl, '/') . '/search/photos', [
                'timeout' => $this->config->requestTimeout,
                'query' => [
                    'query' => $query,
                    'per_page' => max(1, min($limit, 10)),
                    'orientation' => 'landscape',
                    'content_filter' => 'high',
                    'client_id' => $this->config->stockImageApiKey,
                ],
                'headers' => [
                    'Accept-Version' => 'v1',
                ],
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return [
                    'success' => false,
                    'images' => [],
                    'error_code' => 'STOCK_IMAGE_HTTP_ERROR',
                    'error_message' => 'Request stock image gagal dengan status ' . $response->getStatusCode() . '.',
                ];
            }

            $decoded = json_decode($response->getBody(), true);
            $results = is_array($decoded['results'] ?? null) ? $decoded['results'] : [];

            $images = [];
            foreach ($results as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $images[] = [
                    'provider' => 'unsplash',
                    'source_url' => (string) ($item['urls']['regular'] ?? ''),
                    'credit_text' => (string) ($item['user']['name'] ?? ''),
                    'license_info' => 'Unsplash License',
                    'width' => isset($item['width']) ? (int) $item['width'] : null,
                    'height' => isset($item['height']) ? (int) $item['height'] : null,
                    'reference_page' => (string) ($item['links']['html'] ?? ''),
                ];
            }

            if ($images === []) {
                return [
                    'success' => false,
                    'images' => [],
                    'error_code' => 'STOCK_IMAGE_EMPTY_RESULT',
                    'error_message' => 'Tidak ada gambar yang cocok ditemukan.',
                ];
            }

            return [
                'success' => true,
                'images' => $images,
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            $this->logger->error('Stock image search failed.', [
                'provider' => $this->config->stockImageProvider,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'images' => [],
                'error_code' => 'STOCK_IMAGE_REQUEST_EXCEPTION',
                'error_message' => 'Terjadi error saat mencari image.',
            ];
        }
    }

    private function searchWithOpenAI(string $query): array
    {
        if ($this->config->openAIKey === '') {
            return [
                'success' => false,
                'images' => [],
                'error_code' => 'OPENAI_KEY_MISSING',
                'error_message' => 'OpenAI API key belum dikonfigurasi di env.',
            ];
        }

        try {
            $payload = [
                'model' => $this->config->openAIImageModel,
                'prompt' => 'Buat featured image blog profesional untuk topik: ' . $query . '. Tanpa teks besar di gambar.',
                'size' => '1024x1024',
                'n' => 1,
            ];

            $response = $this->http->post(rtrim($this->config->openAIBaseUrl, '/') . '/images/generations', [
                'timeout' => $this->config->requestTimeout,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->openAIKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                $body = mb_substr($response->getBody(), 0, 300);

                return [
                    'success' => false,
                    'images' => [],
                    'error_code' => 'OPENAI_IMAGE_HTTP_ERROR',
                    'error_message' => 'OpenAI image generation gagal dengan status ' . $response->getStatusCode() . '. ' . $body,
                ];
            }

            $decoded = json_decode($response->getBody(), true);
            $b64 = (string) ($decoded['data'][0]['b64_json'] ?? '');
            if ($b64 === '') {
                return [
                    'success' => false,
                    'images' => [],
                    'error_code' => 'OPENAI_IMAGE_EMPTY_RESPONSE',
                    'error_message' => 'OpenAI image response kosong.',
                ];
            }

            $binary = base64_decode($b64, true);
            if ($binary === false) {
                return [
                    'success' => false,
                    'images' => [],
                    'error_code' => 'OPENAI_IMAGE_DECODE_FAILED',
                    'error_message' => 'Gagal decode image dari OpenAI.',
                ];
            }

            $uploadDir = FCPATH . 'uploads/generated';
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = 'feature_' . date('Ymd_His') . '_' . substr(md5($query), 0, 8) . '.png';
            $fullPath = $uploadDir . '/' . $filename;
            file_put_contents($fullPath, $binary);

            $sourceUrl = base_url('uploads/generated/' . $filename);

            return [
                'success' => true,
                'images' => [
                    [
                        'provider' => 'openai',
                        'source_url' => $sourceUrl,
                        'local_path' => 'uploads/generated/' . $filename,
                        'credit_text' => 'AI Generated (OpenAI)',
                        'license_info' => 'Generated content',
                        'width' => 1024,
                        'height' => 1024,
                    ],
                ],
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            $this->logger->error('OpenAI image generation failed.', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'images' => [],
                'error_code' => 'OPENAI_IMAGE_REQUEST_EXCEPTION',
                'error_message' => 'Terjadi error saat generate image AI: ' . $e->getMessage(),
            ];
        }
    }
}
