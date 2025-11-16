<?php
/**
 * VideoController.php
 * 
 * HTTP controller for video-related API endpoints.
 * This controller handles HTTP requests/responses for video operations
 * and delegates business logic to the VideoService.
 */

require_once __DIR__ . '/../services/VideoService.php';

class VideoController
{
    /**
     * @var VideoService Video service
     */
    private $service;

    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->service = new VideoService($pdo);
    }

    /**
     * Handle GET request for video list
     * 
     * @param array $queryParams Query parameters from request
     * @return array JSON response
     */
    public function index($queryParams = [])
    {
        try {
            $options = [
                'limit' => isset($queryParams['limit']) ? (int)$queryParams['limit'] : 20,
                'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0,
                'filters' => [
                    'keyword' => $queryParams['keyword'] ?? '',
                    'genre_id' => $queryParams['genre_id'] ?? null,
                ],
            ];
            
            $result = $this->service->listVideos($options);
            
            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (Exception $e) {
            error_log('VideoController::index error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve videos',
            ];
        }
    }

    /**
     * Handle GET request for single video
     * 
     * @param string $id Video ID or content ID
     * @return array JSON response
     */
    public function show($id)
    {
        try {
            // Try to find by content_id first
            $video = $this->service->getVideoByContentId($id);
            
            // If not found and ID is numeric, try by numeric ID
            if (!$video && is_numeric($id)) {
                $video = $this->service->getVideoById((int)$id);
            }
            
            if (!$video) {
                return [
                    'success' => false,
                    'error' => 'Video not found',
                ];
            }
            
            return [
                'success' => true,
                'data' => $video,
            ];
        } catch (Exception $e) {
            error_log('VideoController::show error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve video',
            ];
        }
    }

    /**
     * Send JSON response
     * 
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     */
    public function respond($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
