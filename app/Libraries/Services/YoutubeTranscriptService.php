<?php

namespace App\Libraries\Services;

use App\Libraries\Contracts\TranscriptProviderInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\ContentPipeline;
use Psr\Log\LoggerInterface;
use Throwable;

class YoutubeTranscriptService implements TranscriptProviderInterface
{
    public function __construct(
        private readonly ContentPipeline $config,
        private readonly CURLRequest $http,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function extractFromYoutubeUrl(string $youtubeUrl): array
    {
        $videoId = $this->extractVideoId($youtubeUrl);
        if ($videoId === null) {
            return [
                'success' => false,
                'transcript' => null,
                'source_data' => [],
                'error_code' => 'INVALID_YOUTUBE_URL',
                'error_message' => 'URL YouTube tidak valid.',
            ];
        }

        try {
            $videoDetails = [];
            $captionTracks = null;

            $watchUrl = 'https://www.youtube.com/watch?v=' . $videoId . '&hl=id';
            $response = $this->http->get($watchUrl, [
                'timeout' => $this->config->requestTimeout,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; BlogGenerator/1.0)',
                    'Accept-Language' => 'id,en-US;q=0.9,en;q=0.8',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return [
                    'success' => false,
                    'transcript' => null,
                    'source_data' => ['video_id' => $videoId],
                    'error_code' => 'YOUTUBE_PAGE_FETCH_FAILED',
                    'error_message' => 'Gagal mengambil halaman YouTube.',
                ];
            }

            $html = $response->getBody();
            $playerResponse = $this->extractPlayerResponse($html);

            if (is_array($playerResponse)) {
                $videoDetails = is_array($playerResponse['videoDetails'] ?? null) ? $playerResponse['videoDetails'] : [];
                $captionTracks = $playerResponse['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? null;
            }

            // Strategy 1: direct timedtext endpoints (more resilient for public captions).
            $transcript = $this->fetchTranscriptFromTimedtext($videoId);

            // Strategy 2: use player response caption baseUrl fallback.
            if (($transcript === null || trim($transcript) === '') && is_array($captionTracks) && $captionTracks !== []) {
                $captionUrl = $this->pickCaptionUrl($captionTracks);
                if ($captionUrl !== null) {
                    $transcript = $this->fetchTranscript($captionUrl);
                }
            }

            if ($transcript === null || trim($transcript) === '') {
                return [
                    'success' => false,
                    'transcript' => null,
                    'source_data' => [
                        'video_id' => $videoId,
                        'title' => $videoDetails['title'] ?? null,
                        'description' => $videoDetails['shortDescription'] ?? null,
                        'comments' => [],
                    ],
                    'error_code' => 'TRANSCRIPT_EMPTY',
                    'error_message' => 'Transcript kosong atau tidak tersedia (caption kemungkinan nonaktif/private).',
                ];
            }

            return [
                'success' => true,
                'transcript' => $transcript,
                'source_data' => [
                    'video_id' => $videoId,
                    'title' => $videoDetails['title'] ?? null,
                    'description' => $videoDetails['shortDescription'] ?? null,
                    'comments' => [],
                ],
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            $this->logger->error('YouTube transcript extraction failed.', [
                'video_id' => $videoId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'transcript' => null,
                'source_data' => ['video_id' => $videoId],
                'error_code' => 'TRANSCRIPT_PROVIDER_ERROR',
                'error_message' => 'Terjadi kesalahan saat mengambil transcript YouTube.',
            ];
        }
    }

    private function fetchTranscriptFromTimedtext(string $videoId): ?string
    {
        $listResponse = $this->http->get('https://video.google.com/timedtext', [
            'timeout' => $this->config->requestTimeout,
            'query' => [
                'type' => 'list',
                'v' => $videoId,
            ],
        ]);

        if ($listResponse->getStatusCode() !== 200) {
            return null;
        }

        $tracks = $this->parseTrackListXml($listResponse->getBody());
        if ($tracks === []) {
            return null;
        }

        $candidateOrder = [
            ['lang' => 'id', 'kind' => null],
            ['lang' => 'en', 'kind' => null],
            ['lang' => 'id', 'kind' => 'asr'],
            ['lang' => 'en', 'kind' => 'asr'],
        ];

        foreach ($candidateOrder as $candidate) {
            foreach ($tracks as $track) {
                if (($track['lang'] ?? null) !== $candidate['lang']) {
                    continue;
                }

                if (($track['kind'] ?? null) !== $candidate['kind']) {
                    continue;
                }

                $transcript = $this->fetchTimedtextTrack($videoId, $track);
                if ($transcript !== null && $transcript !== '') {
                    return $transcript;
                }
            }
        }

        // Last resort: try any available track.
        foreach ($tracks as $track) {
            $transcript = $this->fetchTimedtextTrack($videoId, $track);
            if ($transcript !== null && $transcript !== '') {
                return $transcript;
            }
        }

        return null;
    }

    /**
     * @return list<array{lang:string,kind:?string,name:?string}>
     */
    private function parseTrackListXml(string $xmlBody): array
    {
        $xml = @simplexml_load_string($xmlBody);
        if ($xml === false) {
            return [];
        }

        $tracks = [];

        foreach ($xml->track as $trackNode) {
            $attributes = $trackNode->attributes();
            if ($attributes === null) {
                continue;
            }

            $lang = isset($attributes['lang_code']) ? (string) $attributes['lang_code'] : '';
            if ($lang === '') {
                continue;
            }

            $kind = isset($attributes['kind']) ? (string) $attributes['kind'] : null;
            $name = isset($attributes['name']) ? (string) $attributes['name'] : null;

            $tracks[] = [
                'lang' => $lang,
                'kind' => $kind !== '' ? $kind : null,
                'name' => $name !== '' ? $name : null,
            ];
        }

        return $tracks;
    }

    /**
     * @param array{lang:string,kind:?string,name:?string} $track
     */
    private function fetchTimedtextTrack(string $videoId, array $track): ?string
    {
        $query = [
            'v' => $videoId,
            'lang' => $track['lang'],
        ];

        if (! empty($track['kind'])) {
            $query['kind'] = $track['kind'];
        }

        if (! empty($track['name'])) {
            $query['name'] = $track['name'];
        }

        $response = $this->http->get('https://video.google.com/timedtext', [
            'timeout' => $this->config->requestTimeout,
            'query' => $query,
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        return $this->parseXmlTranscript($response->getBody());
    }

    private function extractVideoId(string $youtubeUrl): ?string
    {
        $url = trim($youtubeUrl);

        if ($url === '') {
            return null;
        }

        $pattern = '#(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})#';
        if (preg_match($pattern, $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function extractPlayerResponse(string $html): ?array
    {
        $pattern = '/ytInitialPlayerResponse\s*=\s*(\{.+?\})\s*;\s*(?:var|const|let|<\/script>)/s';
        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        $json = $matches[1];
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function pickCaptionUrl(array $captionTracks): ?string
    {
        // Prefer Indonesian subtitle if available.
        foreach ($captionTracks as $track) {
            if (! is_array($track)) {
                continue;
            }

            $languageCode = (string) ($track['languageCode'] ?? '');
            if ($languageCode === 'id' && ! empty($track['baseUrl'])) {
                return (string) $track['baseUrl'];
            }
        }

        foreach ($captionTracks as $track) {
            if (is_array($track) && ! empty($track['baseUrl'])) {
                return (string) $track['baseUrl'];
            }
        }

        return null;
    }

    private function fetchTranscript(string $captionUrl): ?string
    {
        $url = $captionUrl;
        if (! str_contains($captionUrl, 'fmt=')) {
            $url .= (str_contains($captionUrl, '?') ? '&' : '?') . 'fmt=json3';
        }

        $response = $this->http->get($url, [
            'timeout' => $this->config->requestTimeout,
        ]);

        if ($response->getStatusCode() === 200) {
            $jsonTranscript = $this->parseJson3Transcript($response->getBody());
            if ($jsonTranscript !== null && $jsonTranscript !== '') {
                return $jsonTranscript;
            }
        }

        // Fallback: parse XML timedtext response.
        $xmlResponse = $this->http->get($captionUrl, [
            'timeout' => $this->config->requestTimeout,
        ]);

        if ($xmlResponse->getStatusCode() !== 200) {
            return null;
        }

        return $this->parseXmlTranscript($xmlResponse->getBody());
    }

    private function parseJson3Transcript(string $body): ?string
    {
        $json = json_decode($body, true);

        if (! is_array($json) || ! is_array($json['events'] ?? null)) {
            return null;
        }

        $chunks = [];
        foreach ($json['events'] as $event) {
            if (! is_array($event) || ! is_array($event['segs'] ?? null)) {
                continue;
            }

            foreach ($event['segs'] as $seg) {
                if (! is_array($seg)) {
                    continue;
                }

                $text = (string) ($seg['utf8'] ?? '');
                if ($text !== '') {
                    $chunks[] = preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $text;
                }
            }
        }

        $transcript = trim(implode(' ', $chunks));

        return $transcript !== '' ? $transcript : null;
    }

    private function parseXmlTranscript(string $xmlBody): ?string
    {
        if (! str_contains($xmlBody, '<text')) {
            return null;
        }

        $xml = @simplexml_load_string($xmlBody);
        if ($xml === false) {
            return null;
        }

        $chunks = [];
        foreach ($xml->text as $textNode) {
            $text = html_entity_decode((string) $textNode, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
            $text = trim($text);

            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        $transcript = trim(implode(' ', $chunks));

        return $transcript !== '' ? $transcript : null;
    }
}
