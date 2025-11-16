<?php
/**
 * Front controller for Video Store API
 *
 * Routes:
 *   GET  /api/items               - list items (keyword, limit, offset)
 *   GET  /api/items/{content_id}  - get single item by content_id
 *   POST /api/admin/fetch         - fetch items from DMM API (requires X-Admin-Token header)
 */

$container = require __DIR__ . '/../src/bootstrap.php';
$pdo = $container['pdo'];
$config = $container['config'];
$itemRepo = $container['itemRepo'];
$dmmClient = $container['dmmClient'];

require_once __DIR__ . '/../src/helpers.php';

// Error reporting (enable more in development)
$appEnv = getenv('APP_ENV') ?: 'development';
$appDebug = getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1';
error_reporting($appDebug ? E_ALL : 0);
ini_set('display_errors', $appDebug ? '1' : '0');

// Default JSON header and CORS
$allowedOrigin = getenv('CORS_ALLOWED_ORIGIN') ?: '*';
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Request variables
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Helper to read JSON body
function getJsonBody() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    // GET /api/items
    if ($requestMethod === 'GET' && preg_match('#^/api/items/?$#', $requestPath)) {
        $filters = [
            'keyword' => $_GET['keyword'] ?? '',
            'genre_id' => $_GET['genre_id'] ?? null,
            'actress_id' => $_GET['actress_id'] ?? null,
            'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 20,
            'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : 0,
            'sort' => $_GET['sort'] ?? 'date',
        ];

        // sanitize
        $filters['limit'] = max(1, min($filters['limit'], 100));
        $filters['offset'] = max(0, $filters['offset']);

        $items = $itemRepo->search($filters);
        $total = $itemRepo->count($filters);

        respondJson([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => $total,
                'limit' => $filters['limit'],
                'offset' => $filters['offset'],
            ]
        ]);
    }

    // GET /api/items/{content_id}
    if ($requestMethod === 'GET' && preg_match('#^/api/items/([^/]+)/?$#', $requestPath, $m)) {
        $contentId = $m[1];
        $item = $itemRepo->findByContentId($contentId);
        if ($item) {
            respondJson(['success' => true, 'data' => $item]);
        } else {
            respondJson(['success' => false, 'error' => 'Item not found'], 404);
        }
    }

    // POST /api/admin/fetch
    if ($requestMethod === 'POST' && preg_match('#^/api/admin/fetch/?$#', $requestPath)) {
        // Accept token in X-Admin-Token header or Authorization: Bearer <token>
        $adminTokenHeader = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = '';

        if (!empty($adminTokenHeader)) {
            $token = trim($adminTokenHeader);
        } elseif (preg_match('/Bearer\s+(.+)/i', $authHeader, $mm)) {
            $token = trim($mm[1]);
        }

        $expected = $config['admin']['token'] ?? getenv('ADMIN_TOKEN') ?: '';

        if (empty($expected) || $token !== $expected) {
            respondJson(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $input = getJsonBody();
        $hits = isset($input['hits']) ? (int)$input['hits'] : 20;
        $offset = isset($input['offset']) ? (int)$input['offset'] : 1;
        $hits = max(1, min($hits, 100));
        $offset = max(1, $offset);

        $start = microtime(true);

        $apiResp = $dmmClient->fetchItems(['hits' => $hits, 'offset' => $offset]);

        if ($apiResp === false || !is_array($apiResp)) {
            // log failure
            $stmt = $pdo->prepare('INSERT INTO fetch_logs (operation, status, items_fetched, message, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute(['fetch_manual', 'error', 0, 'DMM API request failed']);
            respondJson(['success' => false, 'error' => 'DMM API request failed'], 500);
        }

        // Normalize items list
        $itemsList = [];
        if (isset($apiResp['result']['items']) && is_array($apiResp['result']['items'])) {
            $itemsList = $apiResp['result']['items'];
            $totalCount = $apiResp['result']['total_count'] ?? (count($itemsList));
        } elseif (isset($apiResp['items']) && is_array($apiResp['items'])) {
            $itemsList = $apiResp['items'];
            $totalCount = $apiResp['total_count'] ?? count($itemsList);
        } else {
            // Fallback: treat top-level array as items
            $itemsList = is_array($apiResp) ? $apiResp : [];
            $totalCount = count($itemsList);
        }

        $processed = 0;
        foreach ($itemsList as $apiItem) {
            try {
                $itemRepo->upsertFromApi($apiItem);
                $processed++;
            } catch (Exception $e) {
                error_log('Item upsert failed: ' . $e->getMessage());
            }
        }

        $elapsedMs = (int)((microtime(true) - $start) * 1000);
        $stmt = $pdo->prepare('INSERT INTO fetch_logs (operation, status, items_fetched, message, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute(['fetch_manual', 'success', $processed, "Fetched {$processed} items in {$elapsedMs} ms"]);

        respondJson([
            'success' => true,
            'message' => "Successfully fetched and stored {$processed} items",
            'data' => [
                'items_processed' => $processed,
                'total_result_count' => $totalCount,
                'execution_ms' => $elapsedMs
            ]
        ]);
    }

    // No route matched
    respondJson(['success' => false, 'error' => 'Endpoint not found', 'path' => $requestPath, 'method' => $requestMethod], 404);

} catch (Exception $e) {
    error_log('Application error: ' . $e->getMessage());
    respondJson(['success' => false, 'error' => 'Internal server error'], 500);
}