<?php
/**
 * Main API entry point
 * Routes:
 * - GET /api/items - Search items
 * - GET /api/items/{content_id} - Get item details
 * - POST /api/admin/fetch - Fetch items from DMM API (requires admin token)
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/ItemRepository.php';
require_once __DIR__ . '/../src/DmmClient.php';

// Set error handling
error_reporting(APP_DEBUG ? E_ALL : 0);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

// Enable CORS (configure for production environment)
$allowedOrigin = APP_ENV === 'production' ? 
    (env('CORS_ALLOWED_ORIGIN', 'https://yourdomain.com')) : '*';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse request
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '/';

// Remove script name if present
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptName !== '/') {
    $path = substr($path, strlen($scriptName));
}

// Route handling
try {
    $pdo = getDbConnection();
    $itemRepository = new ItemRepository($pdo);
    
    // GET /api/items - Search items
    if ($requestMethod === 'GET' && preg_match('#^/?api/items/?$#', $path)) {
        $filters = [
            'keyword' => $_GET['keyword'] ?? null,
            'genre_id' => $_GET['genre_id'] ?? null,
            'actress_id' => $_GET['actress_id'] ?? null,
            'limit' => isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20,
            'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : 0,
        ];
        
        // Remove null values
        $filters = array_filter($filters, fn($value) => $value !== null);
        
        $items = $itemRepository->search($filters);
        $total = $itemRepository->count($filters);
        
        respondJson([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => $total,
                'limit' => $filters['limit'] ?? 20,
                'offset' => $filters['offset'] ?? 0,
            ],
        ]);
    }
    
    // GET /api/items/{content_id} - Get item details
    if ($requestMethod === 'GET' && preg_match('#^/?api/items/([a-zA-Z0-9_-]+)/?$#', $path, $matches)) {
        $contentId = $matches[1];
        $item = $itemRepository->find($contentId);
        
        if (!$item) {
            respondJson([
                'success' => false,
                'error' => 'Item not found',
            ], 404);
        }
        
        respondJson([
            'success' => true,
            'data' => $item,
        ]);
    }
    
    // POST /api/admin/fetch - Fetch items from DMM API (requires admin token)
    if ($requestMethod === 'POST' && preg_match('#^/?api/admin/fetch/?$#', $path)) {
        // Validate admin token
        if (!validateAdminToken()) {
            respondJson([
                'success' => false,
                'error' => 'Unauthorized. Valid admin token required.',
            ], 401);
        }
        
        // Parse request body
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        
        $dmmClient = new DmmClient(
            DMM_API_ID,
            DMM_AFFILIATE_ID,
            DMM_API_ENDPOINT
        );
        
        try {
            $hits = isset($input['hits']) ? min((int)$input['hits'], 100) : 20;
            $offset = isset($input['offset']) ? (int)$input['offset'] : 1;
            
            $response = $dmmClient->fetchLatest($hits, $offset);
            
            // Store items in database
            $savedCount = 0;
            foreach ($response['items'] as $apiItem) {
                try {
                    $itemRepository->upsertFromApi($apiItem);
                    $savedCount++;
                } catch (Exception $e) {
                    logError("Failed to save item: " . $e->getMessage(), ['item' => $apiItem]);
                }
            }
            
            // Log fetch operation
            $stmt = $pdo->prepare("
                INSERT INTO fetch_logs (fetch_type, status, items_fetched, fetch_date)
                VALUES (:fetch_type, :status, :items_fetched, NOW())
            ");
            $stmt->execute([
                ':fetch_type' => 'manual',
                ':status' => 'success',
                ':items_fetched' => $savedCount,
            ]);
            
            respondJson([
                'success' => true,
                'data' => [
                    'total_fetched' => count($response['items']),
                    'saved_count' => $savedCount,
                    'api_total' => $response['total_count'],
                ],
            ]);
        } catch (Exception $e) {
            // Log fetch failure
            $stmt = $pdo->prepare("
                INSERT INTO fetch_logs (fetch_type, status, items_fetched, error_message, fetch_date)
                VALUES (:fetch_type, :status, 0, :error_message, NOW())
            ");
            $stmt->execute([
                ':fetch_type' => 'manual',
                ':status' => 'failure',
                ':error_message' => $e->getMessage(),
            ]);
            
            respondJson([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    // No route matched - 404
    respondJson([
        'success' => false,
        'error' => 'Endpoint not found',
    ], 404);
    
} catch (Exception $e) {
    logError("Unhandled exception: " . $e->getMessage());
    
    respondJson([
        'success' => false,
        'error' => APP_DEBUG ? $e->getMessage() : 'Internal server error',
    ], 500);
}
