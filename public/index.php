<?php
/**
 * Front Controller
 * Simple routing for API endpoints
 */

// Load dependencies
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/ItemRepository.php';
require_once __DIR__ . '/../src/DmmClient.php';

// Enable error display for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Set JSON content type by default
header('Content-Type: application/json; charset=utf-8');

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Simple routing
try {
    // GET /api/items - List items with optional search
    if ($method === 'GET' && preg_match('#^/api/items/?$#', $path)) {
        $repository = new ItemRepository($pdo);
        
        // Get query parameters
        $keyword = $_GET['keyword'] ?? '';
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        // Fetch items
        $items = $repository->search([
            'keyword' => $keyword,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        $total = $repository->count($keyword);
        
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
    
    // GET /api/items/{content_id} - Get a specific item by content ID
    elseif ($method === 'GET' && preg_match('#^/api/items/([^/]+)/?$#', $path, $matches)) {
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
    
    // POST /admin/fetch-items - Fetch items from DMM API (admin only)
    elseif ($method === 'POST' && $path === '/admin/fetch-items') {
        // Check authentication
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        
        if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }
        
        $adminToken = getenv('ADMIN_TOKEN');
        
        if (empty($adminToken) || $token !== $adminToken) {
            respondJson([
                'success' => false,
                'error' => 'Unauthorized'
            ], 401);
        }
        
        // Get request parameters
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $hits = isset($input['hits']) ? min((int)$input['hits'], 100) : 20;
        $offset = isset($input['offset']) ? (int)$input['offset'] : 1;
        
        // Initialize clients
        $dmmClient = new DmmClient(
            getenv('DMM_API_ID'),
            getenv('DMM_AFFILIATE_ID')
        );
        $repository = new ItemRepository($pdo);
        
        $startTime = microtime(true);
        
        try {
            // Fetch items from DMM API
            $apiResponse = $dmmClient->fetchItems([
                'hits' => $hits,
                'offset' => $offset
            ]);
            
            $items = $apiResponse['result']['items'] ?? [];
            $itemsProcessed = 0;
            
            // Save items to database
            foreach ($items as $item) {
                $repository->upsertFromApi($item);
                $itemsProcessed++;
            }
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            
            // Log the fetch operation
            $stmt = $pdo->prepare("
                INSERT INTO fetch_logs (items_fetched, status, execution_time_ms)
                VALUES (:items_fetched, :status, :execution_time_ms)
            ");
            $stmt->execute([
                'items_fetched' => $itemsProcessed,
                'status' => 'success',
                'execution_time_ms' => $executionTime
            ]);
            
            respondJson([
                'success' => true,
                'data' => [
                    'items_fetched' => $itemsProcessed,
                    'execution_time_ms' => $executionTime
                ]
            ]);
            
        } catch (Exception $e) {
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            
            // Log the error
            $stmt = $pdo->prepare("
                INSERT INTO fetch_logs (items_fetched, status, error_message, execution_time_ms)
                VALUES (:items_fetched, :status, :error_message, :execution_time_ms)
            ");
            $stmt->execute([
                'items_fetched' => 0,
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'execution_time_ms' => $executionTime
            ]);
            
            respondJson([
                'success' => false,
                'error' => 'Failed to fetch items from DMM API',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // Default: 404 Not Found
    else {
        respondJson([
            'success' => false,
            'error' => 'Endpoint not found'
        ], 404);
    }
    
} catch (Exception $e) {
    // General error handler
    error_log('Application error: ' . $e->getMessage());
    
    respondJson([
        'success' => false,
        'error' => 'Internal server error'
    ], 500);
}
