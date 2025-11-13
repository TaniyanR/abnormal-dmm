<?php
/**
 * DMM API Client
 * 
 * Simple client for interacting with DMM Affiliate API v3
 * Documentation: https://affiliate.dmm.com/api/
 */

class DmmClient {
    private $apiId;
    private $affiliateId;
    private $endpoint;
    
    /**
     * Initialize DMM API client
     * 
     * @param string $apiId DMM API ID
     * @param string $affiliateId DMM Affiliate ID
     * @param string $endpoint API endpoint URL
     */
    public function __construct($apiId, $affiliateId, $endpoint) {
        $this->apiId = $apiId;
        $this->affiliateId = $affiliateId;
        $this->endpoint = $endpoint;
    }
    
    /**
     * Fetch items from DMM ItemList API
     * 
     * @param array $params Query parameters (site, service, floor, hits, offset, sort, etc.)
     * @return array|null Decoded JSON response or null on failure
     */
    public function fetchItems($params = []) {
        // Validate API credentials
        if (empty($this->apiId) || empty($this->affiliateId)) {
            error_log('DMM API credentials not configured');
            return null;
        }
        
        // Default parameters
        $defaultParams = [
            'api_id' => $this->apiId,
            'affiliate_id' => $this->affiliateId,
            'site' => 'FANZA',
            'service' => 'digital',
            'floor' => 'videoa',
            'hits' => 20,
            'offset' => 1,
            'sort' => 'date',
            'output' => 'json'
        ];
        
        // Merge with provided parameters
        $queryParams = array_merge($defaultParams, $params);
        
        // Build query string
        $queryString = http_build_query($queryParams);
        $url = $this->endpoint . '?' . $queryString;
        
        // Make API request
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 30,
                    'header' => 'User-Agent: VideoStore/1.0'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                error_log('DMM API request failed: ' . $url);
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('DMM API JSON decode error: ' . json_last_error_msg());
                return null;
            }
            
            return $data;
            
        } catch (Exception $e) {
            error_log('DMM API exception: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get recent items
     * 
     * @param int $hits Number of items to fetch
     * @param int $offset Offset for pagination
     * @return array|null API response or null on failure
     */
    public function getRecentItems($hits = 20, $offset = 1) {
        return $this->fetchItems([
            'hits' => $hits,
            'offset' => $offset,
            'sort' => 'date'
        ]);
    }
}
