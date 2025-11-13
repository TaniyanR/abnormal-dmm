<?php
/**
 * DMM API Client
 * Handles communication with the DMM Affiliate API (ItemList v3)
 */

class DmmClient {
    private $apiId;
    private $affiliateId;
    private $baseUrl = 'https://api.dmm.com/affiliate/v3/ItemList';

    /**
     * Constructor
     *
     * @param string $apiId DMM API ID
     * @param string $affiliateId DMM Affiliate ID
     */
    public function __construct($apiId, $affiliateId) {
        $this->apiId = $apiId;
        $this->affiliateId = $affiliateId;
    }

    /**
     * Fetch items from DMM API
     *
     * @param array $params Additional query parameters (e.g., hits, offset, sort, keyword)
     * @return array API response data
     * @throws Exception If API request fails
     */
    public function fetchItems($params = []) {
        // Build query parameters
        $query = array_merge([
            'api_id' => $this->apiId,
            'affiliate_id' => $this->affiliateId,
            'site' => 'FANZA',
            'hits' => 20,
            'offset' => 1,
            'sort' => 'date',
            'output' => 'json'
        ], $params);

        $url = $this->baseUrl . '?' . http_build_query($query);

        // Make HTTP request
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'DMM-Video-Store/1.0'
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('Failed to fetch data from DMM API');
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse DMM API response');
        }

        // Check for API errors
        if (isset($data['result']['status']) && $data['result']['status'] !== 200) {
            $message = $data['result']['message'] ?? 'Unknown API error';
            throw new Exception("DMM API error: $message");
        }

        return $data;
    }

    /**
     * Get item details by content ID
     *
     * @param string $contentId Content ID to fetch
     * @return array|null Item data or null if not found
     */
    public function getItemByContentId($contentId) {
        try {
            $data = $this->fetchItems(['cid' => $contentId]);
            
            if (isset($data['result']['items']) && count($data['result']['items']) > 0) {
                return $data['result']['items'][0];
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Failed to fetch item $contentId: " . $e->getMessage());
            return null;
        }
    }
}
