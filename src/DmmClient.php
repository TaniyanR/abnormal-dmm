<?php
/**
 * src/DmmClient.php
 * Simple DMM / FANZA API client for ItemList (v3)
 *
 * The client accepts optional constructor parameters (apiId, affiliateId, endpoint).
 * If not provided, values are read from environment variables:
 *  - DMM_API_ID
 *  - DMM_AFFILIATE_ID
 *  - DMM_API_ENDPOINT (optional, defaults to ItemList endpoint)
 *
 * Methods:
 *  - fetchItems(array $params): array|false  (returns decoded response array on success, false on failure)
 *  - getRecentItems(int $hits, int $offset): array|false
 *  - getItemByContentId(string $contentId): array|null
 */

class DmmClient
{
    private $apiId;
    private $affiliateId;
    private $endpoint;

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

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'header' => "User-Agent: VideoStore/1.0\r\nAccept: application/json\r\n"
            ]
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            error_log("DmmClient: HTTP request failed for URL: {$url}");
            return false;
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('DmmClient: JSON decode error: ' . json_last_error_msg());
            return false;
        }

        // Basic validation: ensure result exists
        if (!isset($data['result'])) {
            error_log('DmmClient: Unexpected API response (missing result).');
            return false;
        }

        return $data;
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
            $normalizedItems[] = [
                'content_id' => $item['content_id'] ?? ($item['cid'] ?? ''),
                'title' => $item['title'] ?? '',
                'description' => $item['iteminfo']['product_description'] ?? ($item['description'] ?? null),
                'sample_video_url' => $item['sampleMovieURL']['size_560_360'] ?? ($item['sampleMovieURL']['size_476_306'] ?? null),
                'price' => isset($item['prices']['price']) ? (int)preg_replace('/[^0-9]/', '', (string)$item['prices']['price']) : null,
                'release_date' => isset($item['date']) ? date('Y-m-d', strtotime($item['date'])) : null,
                'affiliate_url' => $item['affiliateURL'] ?? ($item['affiliate_url'] ?? null),
                'thumbnail_url' => $item['imageURL']['large'] ?? ($item['imageURL']['small'] ?? null),
                'genres' => $item['iteminfo']['genre'] ?? [],
                'actresses' => $item['iteminfo']['actress'] ?? [],
                'maker' => $item['iteminfo']['maker'][0] ?? null,
            ];
        }

        return [
            'total_count' => $apiResponse['result']['total_count'] ?? 0,
            'items' => $normalizedItems,
        ];
    }
}