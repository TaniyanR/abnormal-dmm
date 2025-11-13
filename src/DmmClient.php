<?php
/**
 * DmmClient - DMM API client for fetching items
 */

class DmmClient
{
    private string $apiId;
    private string $affiliateId;
    private string $apiEndpoint;

    public function __construct(
        string $apiId,
        string $affiliateId,
        string $apiEndpoint = 'https://api.dmm.com/affiliate/v3/ItemList'
    ) {
        $this->apiId = $apiId;
        $this->affiliateId = $affiliateId;
        $this->apiEndpoint = $apiEndpoint;
    }

    /**
     * Fetch items from DMM API
     *
     * @param array $params Query parameters (hits, offset, keyword, etc.)
     * @return array API response with items
     * @throws RuntimeException If API call fails
     */
    public function fetchItems(array $params = []): array
    {
        if (empty($this->apiId) || empty($this->affiliateId)) {
            throw new RuntimeException("DMM API credentials not configured");
        }

        // Build query parameters
        $queryParams = array_merge([
            'api_id' => $this->apiId,
            'affiliate_id' => $this->affiliateId,
            'site' => 'FANZA',
            'service' => 'digital',
            'floor' => 'videoa',
            'hits' => 20,
            'offset' => 1,
            'output' => 'json',
        ], $params);

        $url = $this->apiEndpoint . '?' . http_build_query($queryParams);

        // Make API request
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: abnormal-dmm/1.0',
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new RuntimeException("DMM API request failed: " . ($error['message'] ?? 'Unknown error'));
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON response from DMM API");
        }

        if (isset($data['result']['status']) && $data['result']['status'] !== 200) {
            throw new RuntimeException(
                "DMM API error: " . ($data['result']['message'] ?? 'Unknown error')
            );
        }

        return $this->normalizeResponse($data);
    }

    /**
     * Normalize DMM API response to internal format
     *
     * @param array $apiResponse Raw API response
     * @return array Normalized response
     */
    private function normalizeResponse(array $apiResponse): array
    {
        $items = $apiResponse['result']['items'] ?? [];
        $normalizedItems = [];

        foreach ($items as $item) {
            $normalizedItems[] = [
                'content_id' => $item['content_id'] ?? '',
                'title' => $item['title'] ?? '',
                'description' => $item['iteminfo']['genre'][0]['name'] ?? null,
                // Prefer higher resolution video (560x360) over lower resolution (476x306)
                'sample_video_url' => $item['sampleMovieURL']['size_560_360'] ?? 
                                     $item['sampleMovieURL']['size_476_306'] ?? null,
                'price' => isset($item['prices']['price']) ? (int)str_replace(',', '', $item['prices']['price']) : null,
                'release_date' => isset($item['date']) ? date('Y-m-d', strtotime($item['date'])) : null,
                'affiliate_url' => $item['affiliateURL'] ?? null,
                'thumbnail_url' => $item['imageURL']['large'] ?? $item['imageURL']['small'] ?? null,
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

    /**
     * Search items by keyword
     *
     * @param string $keyword Search keyword
     * @param int $hits Number of items to fetch
     * @param int $offset Offset for pagination
     * @return array API response with items
     */
    public function searchByKeyword(string $keyword, int $hits = 20, int $offset = 1): array
    {
        return $this->fetchItems([
            'keyword' => $keyword,
            'hits' => $hits,
            'offset' => $offset,
        ]);
    }

    /**
     * Fetch items by actress ID
     *
     * @param string $actressId Actress ID
     * @param int $hits Number of items to fetch
     * @param int $offset Offset for pagination
     * @return array API response with items
     */
    public function fetchByActress(string $actressId, int $hits = 20, int $offset = 1): array
    {
        return $this->fetchItems([
            'actress' => $actressId,
            'hits' => $hits,
            'offset' => $offset,
        ]);
    }

    /**
     * Fetch latest items
     *
     * @param int $hits Number of items to fetch
     * @param int $offset Offset for pagination
     * @return array API response with items
     */
    public function fetchLatest(int $hits = 20, int $offset = 1): array
    {
        return $this->fetchItems([
            'sort' => 'date',
            'hits' => $hits,
            'offset' => $offset,
        ]);
    }
}
