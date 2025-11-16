<?php
/**
 * VideoService.php
 * 
 * Business logic layer for video operations.
 * This service handles video-related business logic, validation, and orchestration
 * between models and external services.
 */

require_once __DIR__ . '/../models/VideoModel.php';
require_once __DIR__ . '/../VideoStoreConfig.php';

class VideoService
{
    /**
     * @var VideoModel Video model
     */
    private $videoModel;

    /**
     * @var array Configuration
     */
    private $config;

    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->videoModel = new VideoModel($pdo);
        $this->config = VideoStoreConfig::all();
    }

    /**
     * Get video details by ID
     * 
     * @param int $id Video ID
     * @return array|null Video data with additional processing
     */
    public function getVideoById($id)
    {
        $video = $this->videoModel->findById($id);
        
        if (!$video) {
            return null;
        }
        
        return $this->enrichVideoData($video);
    }

    /**
     * Get video details by content ID
     * 
     * @param string $contentId Content ID
     * @return array|null Video data with additional processing
     */
    public function getVideoByContentId($contentId)
    {
        $video = $this->videoModel->findByContentId($contentId);
        
        if (!$video) {
            return null;
        }
        
        return $this->enrichVideoData($video);
    }

    /**
     * List videos with pagination and filters
     * 
     * @param array $options Query options (limit, offset, sort, filters)
     * @return array Paginated video list with metadata
     */
    public function listVideos($options = [])
    {
        $limit = $options['limit'] ?? $this->config['items_per_page'];
        $offset = $options['offset'] ?? 0;
        
        // Validate and sanitize limit
        $limit = min($limit, $this->config['max_items_per_page']);
        $limit = max(1, $limit);
        
        $videos = $this->videoModel->all($limit, $offset);
        $total = $this->videoModel->count($options['filters'] ?? []);
        
        return [
            'items' => array_map([$this, 'enrichVideoData'], $videos),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total,
        ];
    }

    /**
     * Enrich video data with additional computed fields
     * 
     * @param array $video Raw video data
     * @return array Enriched video data
     */
    private function enrichVideoData($video)
    {
        // Format dates if needed
        if (isset($video['date'])) {
            $video['formatted_date'] = date('Y-m-d', strtotime($video['date']));
        }
        
        return $video;
    }

    /**
     * Validate video search parameters
     * 
     * @param array $params Search parameters
     * @return array Validated parameters
     */
    public function validateSearchParams($params)
    {
        $validated = [];
        
        if (isset($params['limit'])) {
            $validated['limit'] = max(1, min((int)$params['limit'], $this->config['max_items_per_page']));
        }
        
        if (isset($params['offset'])) {
            $validated['offset'] = max(0, (int)$params['offset']);
        }
        
        if (isset($params['sort']) && in_array($params['sort'], $this->config['allowed_sort_fields'])) {
            $validated['sort'] = $params['sort'];
        }
        
        return $validated;
    }
}
