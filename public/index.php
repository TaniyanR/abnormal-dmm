<?php
/**
 * Front controller for Video Store API
 * Routes:
 *   GET  /api/items               - list items (keyword, limit, offset)
 *   GET  /api/items/{content_id}  - get single item by content_id
 *   POST /admin/fetch-items       - fetch items from DMM API (requires ADMIN_TOKEN)
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/ItemRepository.php';
require_once __DIR__ . '/../src/DmmClient.php';

// Error display for development (disable/show less in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Default JSON response header and CORS for development
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Use request variables
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// $pdo is provided by src/bootstrap.php
// If bootstrap exported differently, adjust accordingly.
try {
    // GET /api/items - list with optional search
    if ($requestMethod === 'GET' && preg_match('#^/api/items/?$#', $requestPath)) {
        $keyword = $_GET['keyword'] ?? '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $sort = $_GET['sort'] ?? 'date';

        // sanitize
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $page = intdiv($offset, $limit) + 1;

        $repo = new ItemRepository($pdo);
        // ItemRepository::search may accept (keyword, page, perPage, sort) depending on implementation
        $result = $repo->search($keyword, $page, $limit, $sort);

        // If search returns items and total_count, pass through; else try to normalize
        if (is_array($result) && isset($result['items'])) {
            respondJson([
                'success' => true,
                'data' => [
                    'items' => $result['items'],
                    'total' => $result['total_count'] ?? (count($result['items'])),
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);
        } else {
            // fallback: assume search returned plain array of items
            respondJson([
                'success' => true,
                'data' => [
                    'items' => $result,
                    'total' => is_array($result) ? count($result) : 0,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);
        }
        exit;
    }

    // GET /api/items/{content_id} - item detail
    if ($requestMethod === 'GET' && preg_match('#^/api/items/([^/]+)/?$#', $requestPath, $m)) {
        $contentId = $m[1];
        $repo = new ItemRepository($pdo);
        $item = $repo->findByContentId($contentId);
        if ($item) {
            respondJson(['success' => true, 'data' => $item]);
        } else {
            respondJson(['success' => false, 'error' => 'Item not found'], 404);
        }
        exit;
    }

    // POST /admin/fetch-items - admin action to fetch from DMM
    if ($requestMethod === 'POST' && preg_match('#^/admin/fetch-items/?$#', $requestPath)) {
        // Authorization: Bearer <token>
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['HTTP_AUTH'] ?? '');
        $token = '';
        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $mm)) {
            $token = trim($mm[1]);
        }

        $adminToken = getenv('ADMIN_TOKEN') ?: '';
        if (!$adminToken || $token !== $adminToken) {
            respondJson(['success' => false, 'error' => 'Unauthorized'], 401);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $hits = isset($input['hits']) ? (int)$input['hits'] : 20;
        $offset = isset($input['offset']) ? (int)$input['offset'] : 1;
        $hits = max(1, min($hits, 100));
        $offset = max(1, $offset);

        $dmm = new DmmClient(); // reads API keys from env by default
        $repo = new ItemRepository($pdo);

        $start = microtime(true);
        try {
            $items = $dmm->fetchItems([
                'hits' => $hits,
                'offset' => $offset
            ]);
            if ($items === false) {
                // log
                $stmt = $pdo->prepare('INSERT INTO fetch_logs (operation, status, items_fetched, message) VALUES (?, ?, ?, ?)');
                $stmt->execute(['fetch', 'error', 0, 'DMM API request failed']);
                respondJson(['success' => false, 'error' => 'DMM fetch failed'], 500);
                exit;
            }

            $processed = 0;
            foreach ($items as $apiItem) {
                try {
                    $repo->upsertFromApi($apiItem);
                    $processed++;
                } catch (Exception $e) {
                    error_log('upsert error: ' . $e->getMessage());
                }
            }

            $elapsed = (int)((microtime(true) - $start) * 1000);
            $stmt = $pdo->prepare('INSERT INTO fetch_logs (operation, status, items_fetched, message) VALUES (?, ?, ?, ?)');
            $stmt->execute(['fetch', 'success', $processed, "Fetched {$processed} items in {$elapsed} ms"]);

            respondJson([
                'success' => true,
                'message' => "Successfully fetched and stored {$processed} items",
                'data' => [
                    'items_processed' => $processed,
                    'execution_ms' => $elapsed
                ]
            ]);
            exit;
        } catch (Exception $e) {
            $elapsed = (int)((microtime(true) - $start) * 1000);
            $stmt = $pdo->prepare('INSERT INTO fetch_logs (operation, status, items_fetched, message) VALUES (?, ?, ?, ?)');
            $stmt->execute(['fetch', 'error', 0, $e->getMessage()]);
            respondJson(['success' => false, 'error' => 'Failed to fetch items', 'message' => $e->getMessage()], 500);
            exit;
        }
    }

    // No route matched
    respondJson(['success' => false, 'error' => 'Endpoint not found', 'path' => $requestPath, 'method' => $requestMethod], 404);

} catch (Exception $e) {
    error_log('Application error: ' . $e->getMessage());
    respondJson(['success' => false, 'error' => 'Internal server error'], 500);
}