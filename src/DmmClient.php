<?php
/**
 * src/DmmClient.php
 * Simple DMM / FANZA API client for ItemList (v3)
 *
 * Improved: use cURL with retries, HTTP status checking, better error logs,
 * and more defensive normalization.
 */

class DmmClient
{
    private $apiId;
    private $affiliateId;
    private $endpoint;
    private $userAgent = 'VideoStore/1.0';

    public function __construct(string $apiId = null, string $affiliateId = null, string $endpoint = null)
    {
        $this->apiId = $apiId ?: getenv('DMM_API_ID') ?: '';
        $this->affiliateId = $affiliateId ?: getenv('DMM_AFFILIATE_ID') ?: '';
        $this->endpoint = $endpoint ?: getenv('DMM_API_ENDPOINT') ?: 'https://api.dmm.com/affiliate/v3/ItemList';
    }

    /**
     * Fetch items from DMM ItemList API
     *
     * @param array $params Query parameters (hits, offset, keyword, site, service, floor, sort, etc.)
     * @return array|false Decoded JSON response array on success, false on failure
     */
    public function fetchItems(array $params = [])
    {
        // Ensure credentials exist
        if (empty($this->apiId) || empty($this->affiliateId)) {
            error_log('DmmClient: API credentials are not configured.');
            return false;
        }

        $default = [
            'api_id' => $this->apiId,
            'affiliate_id' => $this->affiliateId,
            'site' => $params['site'] ?? 'FANZA',
            'service' => $params['service'] ?? 'digital',
            'floor' => $params['floor'] ?? 'videoa',
            'hits' => isset($params['hits']) ? (int)$params['hits'] : 20,
            'offset' => isset($params['offset']) ? (int)$params['offset'] : 1,
            'sort' => $params['sort'] ?? 'date',
            'output' => 'json',
        ];

        // Merge defaults with user-supplied params (user params override defaults)
        $query = array_merge($default, $params);

        $url = $this->endpoint . '?' . http_build_query($query);

        $raw = $this->httpGet($url);
        if ($raw === false) {
            // error already logged in httpGet
            return false;
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('DmmClient: JSON decode error: ' . json_last_error_msg());
            return false;
        }

        // Basic validation: ensure result exists
        if (!isset($data['result'])) {
            error_log('DmmClient: Unexpected API response (missing result). Raw response: ' . substr($raw, 0, 500));
            return false;
        }

        return $data;
    }

    /**
     * HTTP GET helper using cURL with retries
     *
     * @param string $url
     * @param int $retries
     * @return string|false Raw response body or false on failure
     */
    private function httpGet(string $url, int $retries = 3)
    {
        $attempt = 0;
        $lastErr = null;

        while ($attempt < $retries) {
            $attempt++;
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                ],
            ]);

            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';

            curl_close($ch);

            if ($errno) {
                $lastErr = "cURL error ({$errno}): {$error}";
                error_log("DmmClient: attempt {
                    $attempt} failed: {
                    $lastErr}");
                // small backoff
                usleep(200000 * $attempt);
                continue;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $lastErr = "HTTP status {
                    $httpCode}";
                error_log("DmmClient: attempt {$attempt} returned non-2xx status: {$httpCode} for URL: {$url}");
                usleep(200000 * $attempt);
                continue;
            }

            // Prefer JSON content-type check but don't strictly require it (some APIs may omit)
            if (stripos($contentType, 'application/json') === false && strlen($body) > 0 && $body[0] !== '{' && $body[0] !== '[') {
                error_log("DmmClient: attempt {$attempt} unexpected content-type: {$contentType} for URL: {$url}");
                // still allow attempt to decode, but log as warning
            }

            // success
            return $body;
        }

        error_log('DmmClient: all attempts failed. Last error: ' . ($lastErr ?? 'unknown'));
        return false;
    }

    /**
     * Convenience method to fetch recent items (sorted by date).
     *
     * @param int $hits
     * @param int $offset
     * @return array|false
     */
    public function getRecentItems(int $hits = 20, int $offset = 1)
    {
        return $this->fetchItems([
            'hits' => $hits,
            'offset' => $offset,
            'sort' => 'date'
        ]);
    }

    /**
     * Fetch a single item by content ID (cid)
     *
     * @param string $contentId
     * @return array|null First item array on success, null on not found or failure
     */
    public function getItemByContentId(string $contentId)
    {
        $resp = $this->fetchItems([
            'cid' => $contentId,
            'hits' => 1
        ]);

        if ($resp === false) return null;
        if (isset($resp['result']['items']) && is_array($resp['result']['items']) && count($resp['result']['items']) > 0) {
            return $resp['result']['items'][0];
        }

        return null;
    }

    /**
     * Normalize a DMM API response into a simplified array (optional helper)
     *
     * @param array $apiResponse
     * @return array ['total_count' => int, 'items' => array]
     */
    public function normalizeResponse(array $apiResponse)
    {
        $items = $apiResponse['result']['items'] ?? [];
        $normalizedItems = [];

        foreach ($items as $item) {
            $contentId = $item['content_id'] ?? ($item['cid'] ?? '');
            $title = $item['title'] ?? ($item['title_short'] ?? '');
            $description = $item['iteminfo']['product_description'] ?? $item['description'] ?? ($item['item_description'] ?? '');
            // sample video URL: choose best available
            $sampleVideoUrl = null;
            if (!empty($item['sampleMovieURL']) && is_array($item['sampleMovieURL'])) {
                // prefer larger sizes when available
                $sampleVideoUrl = $item['sampleMovieURL']['size_560_360'] ?? $item['sampleMovieURL']['size_476_306'] ?? null;
            } elseif (!empty($item['sampleMovieURL'])) {
                $sampleVideoUrl = (string)$item['sampleMovieURL'];
            }

            // price extraction (defensive)
            $price = null;
            if (!empty($item['prices']['price'])) {
                $priceStr = (string)$item['prices']['price'];
                $price = (int)preg_replace('/[^0-9]/', '', $priceStr);
            } elseif (!empty($item['price'])) {
                $price = (int)$item['price'];
            }

            $releaseDate = null;
            if (!empty($item['date'])) {
                $releaseDate = date('Y-m-d', strtotime($item['date']));
            } elseif (!empty($item['release_date'])) {
                $releaseDate = date('Y-m-d', strtotime($item['release_date']));
            }

            $thumbnail = null;
            if (!empty($item['imageURL']) && is_array($item['imageURL'])) {
                $thumbnail = $item['imageURL']['large'] ?? $item['imageURL']['small'] ?? null;
            } elseif (!empty($item['images'])) {
                $thumbnail = is_array($item['images']) ? ($item['images']['large'] ?? $item['images']['small'] ?? null) : null;
            }

            $genres = [];
            if (!empty($item['iteminfo']['genre'])) {
                $genres = is_array($item['iteminfo']['genre']) ? $item['iteminfo']['genre'] : [$item['iteminfo']['genre']];
            }

            $actresses = [];
            if (!empty($item['iteminfo']['actress'])) {
                $actresses = is_array($item['iteminfo']['actress']) ? $item['iteminfo']['actress'] : [$item['iteminfo']['actress']];
            }

            $maker = null;
            if (!empty($item['iteminfo']['maker'])) {
                if (is_array($item['iteminfo']['maker'])) {
                    $maker = $item['iteminfo']['maker'][0] ?? null;
                } else {
                    $maker = $item['iteminfo']['maker'];
                }
            }

            $normalizedItems[] = [
                'content_id' => $contentId,
                'title' => $title,
                'description' => $description,
                'sample_video_url' => $sampleVideoUrl,
                'price' => $price,
                'release_date' => $releaseDate,
                'affiliate_url' => $item['affiliateURL'] ?? ($item['affiliate_url'] ?? null),
                'thumbnail_url' => $thumbnail,
                'genres' => $genres,
                'actresses' => $actresses,
                'maker' => $maker,
            ];
        }

        return [
            'total_count' => (int)($apiResponse['result']['total_count'] ?? 0),
            'items' => $normalizedItems,
        ];
    }
}