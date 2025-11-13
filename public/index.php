<?php
/**
 * Front Controller
 * 
 * Simple routing for Video Store API
 * Endpoints:
 *   GET  /api/items - List items with optional search
 *   GET  /api/items/{content_id} - Get specific item
 *   POST /admin/fetch-items - Fetch items from DMM API (requires ADMIN_TOKEN)
 */

// Load dependencies
require_once __DIR__ . '/../src/helpers.php';
$bootstrap = require __DIR__ . '/../src/bootstrap.php';
$pdo = $bootstrap['pdo'];
$config = $bootstrap['config'];

require_once __DIR__ . '/../src/ItemRepository.php';
require_once __DIR__ . '/../src/DmmClient.php';

// Set CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse request URI and method
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Simple routing
try {
    // GET /api/items - List items with optional search
    if ($requestMethod === 'GET' && preg_match('#^/api/items/?$#', $requestPath)) {
        $repository = new ItemRepository($pdo);
        
        // Get query parameters
        $keyword = $_GET['keyword'] ?? '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        // Validate limits
        $limit = max(1, min($limit, 100)); // Between 1 and 100
        $offset = max(0, $offset);
        
        $filters = [
            'keyword' => $keyword,
            'limit' => $limit,
            'offset' => $offset
        ];
        
        $items = $repository->search($filters);
        $total = $repository->count($filters);
        
        respondJson([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
    }
    
    // GET /api/items/{content_id} - Get specific item
    elseif ($requestMethod === 'GET' && preg_match('#^/api/items/([^/]+)/?$#', $requestPath, $matches)) {
        $contentId = $matches[1];
        $repository = new ItemRepository($pdo);
        
        $item = $repository->findByContentId($contentId);
        
        if ($item) {
            respondJson([
                'success' => true,
                'data' => $item
            ]);
        } else {
            respondJson([
                'success' => false,
                'error' => 'Item not found'
            ], 404);
        }
    }
    
    // POST /admin/fetch-items - Fetch items from DMM API
    elseif ($requestMethod === 'POST' && preg_match('#^/admin/fetch-items/?$#', $requestPath)) {
        // Verify admin token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
        
        $adminToken = $config['admin']['token'];
        
        if (empty($adminToken)) {
            respondJson([
                'success' => false,
                'error' => 'Admin token not configured'
            ], 500);
        }
        
        if ($token !== $adminToken) {
            respondJson([
                'success' => false,
                'error' => 'Unauthorized'
            ], 401);
        }
        
        // Parse request body
        $input = json_decode(file_get_contents('php://input'), true);
        $hits = isset($input['hits']) ? (int)$input['hits'] : 20;
        $offset = isset($input['offset']) ? (int)$input['offset'] : 1;
        
        // Initialize DMM client
        $dmmClient = new DmmClient(
            $config['dmm']['api_id'],
            $config['dmm']['affiliate_id'],
            $config['dmm']['api_endpoint']
        );
        
        // Fetch items from DMM API
        $apiResponse = $dmmClient->getRecentItems($hits, $offset);
        
        if (!$apiResponse || !isset($apiResponse['result'])) {
            // Log fetch failure
            $stmt = $pdo->prepare('INSERT INTO fetch_logs (fetch_type, status, message) VALUES (?, ?, ?)');
            $stmt->execute(['manual', 'error', 'DMM API request failed']);
            
            respondJson([
                'success' => false,
                'error' => 'Failed to fetch items from DMM API'
            ], 500);
        }
        
        // Process and store items
        $repository = new ItemRepository($pdo);
        $itemsProcessed = 0;
        
        if (isset($apiResponse['result']['items']) && is_array($apiResponse['result']['items'])) {
            foreach ($apiResponse['result']['items'] as $apiItem) {
                try {
                    $repository->upsertFromApi($apiItem);
                    $itemsProcessed++;
                } catch (Exception $e) {
                    error_log('Failed to process item: ' . $e->getMessage());
                }
            }
        }
        
        // Log successful fetch
        $stmt = $pdo->prepare('INSERT INTO fetch_logs (fetch_type, status, items_fetched, message) VALUES (?, ?, ?, ?)');
        $stmt->execute(['manual', 'success', $itemsProcessed, "Fetched $itemsProcessed items"]);
        
        respondJson([
            'success' => true,
            'message' => "Successfully fetched and stored $itemsProcessed items",
            'data' => [
                'items_processed' => $itemsProcessed,
                'total_result_count' => $apiResponse['result']['total_count'] ?? 0
            ]
        ]);
    }
    
    // 404 - Route not found
    else {
        respondJson([
            'success' => false,
            'error' => 'Endpoint not found',
            'path' => $requestPath,
            'method' => $requestMethod
        ], 404);
    }
    
} catch (Exception $e) {
    // Log error
    error_log('Application error: ' . $e->getMessage());
    
    // Return generic error response
    respondJson([
        'success' => false,
        'error' => 'Internal server error'
    ], 500);
}
