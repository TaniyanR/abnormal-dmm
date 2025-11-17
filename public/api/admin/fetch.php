<?php
declare(strict_types=1);

// public/api/admin/fetch.php
// Admin trigger endpoint for manual fetch. Safe default: returns the planned requests. If run=true and DMM credentials exist, attempts to perform requests (best-effort).

header('Content-Type: application/json; charset=utf-8');

// bootstrap to obtain config / pdo if available
$bootstrapPath = __DIR__ . '/../../src/bootstrap.php';
if (file_exists($bootstrapPath)) {
    $container = require $bootstrapPath; // may set $GLOBALS['config'] or return array/container
    $config = $container['config'] ?? ($GLOBALS['config'] ?? []);
    $pdo = $container['pdo'] ?? ($GLOBALS['pdo'] ?? null);
} else {
    $config = file_exists(__DIR__ . '/../../.env.php') ? require __DIR__ . '/../../.env.php' : ($GLOBALS['config'] ?? []);
    $pdo = $GLOBALS['pdo'] ?? null;
}

// helper
function jsonErr(int $code, string $msg) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// get admin token from config
$adminToken = $config['ADMIN_TOKEN'] ?? ($config['admin']['token'] ?? getenv('ADMIN_TOKEN') ?: null);

// read Authorization header or ?token
$headers = function_exists('getallheaders') ? getallheaders() : [];
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($headers['Authorization'] ?? ($headers['authorization'] ?? null));
$provided = null;
if (!empty($auth) && preg_match('/Bearer\s+(.*)$/i', $auth, $m)) {
    $provided = trim($m[1]);
} elseif (!empty($_GET['token'])) {
    $provided = trim((string)$_GET['token']);
}

// simple auth: if adminToken set, require match. If not set, allow (dev convenience)
if (!empty($adminToken) && (!is_string($provided) || !hash_equals((string)$adminToken, (string)$provided))) {
    jsonErr(401, 'Unauthorized: invalid admin token');
}

// parse request body
$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
if (!is_array($body)) $body = [];

// load settings (DB preferred)
$settings = [];
if ($pdo instanceof PDO) {
    try {
        // support both schemas
        $stmt = $pdo->query("SELECT `key`,`value` FROM `api_settings`");
        if ($stmt !== false) {
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$r['key']] = $r['value'];
            }
        }
    } catch (Throwable $e) {
        // try legacy
        try {
            $stmt = $pdo->query("SELECT `setting_key` AS `key`, `setting_value` AS `value` FROM `api_settings`");
            if ($stmt !== false) while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $settings[$r['key']] = $r['value'];
        } catch (Throwable $e2) {
            $settings = [];
        }
    }
}
// file fallback
if (empty($settings)) {
    $f = __DIR__ . '/../../.api_settings.json';
    if (file_exists($f)) {
        $json = json_decode((string)@file_get_contents($f), true);
        if (is_array($json)) $settings = $json;
    }
}

// merge defaults
$defaults = [
    'API_FETCH_TOTAL' => 100,
    'API_FETCH_COUNT' => 20,
    'API_RUN_INTERVAL' => 3600,
    'API_SORT' => 'date'
];
foreach ($defaults as $k => $v) { if (!isset($settings[$k])) $settings[$k] = $v; }

// determine parameters
$total = isset($body['total']) ? (int)$body['total'] : (int)$settings['API_FETCH_TOTAL'];
$hits = isset($body['hits']) ? (int)$body['hits'] : (int)$settings['API_FETCH_COUNT'];
$offset = isset($body['offset']) ? (int)$body['offset'] : 1;
$run = !empty($body['run']);
if ($hits < 1) $hits = (int)$defaults['API_FETCH_COUNT'];
if ($total < 1) $total = (int)$defaults['API_FETCH_TOTAL'];

$pages = [];
$pagesCount = (int)ceil($total / $hits);
for ($i = 0; $i < $pagesCount; $i++) {
    $pages[] = [
        'hits' => $hits,
        'offset' => $i * $hits + $offset
    ];
}

$response = [
    'success' => true,
    'planned_requests' => $pages,
    'total' => $total,
    'hits' => $hits,
    'run' => $run,
    'note' => 'Endpoint plans paginated requests. Set run=true to attempt execution if DMM credentials are configured.'
];

// If run requested, attempt fetch only when DMM credentials present
$dmmId = $config['DMM_API_ID'] ?? ($config['dmm']['api_id'] ?? null);
$dmmAff = $config['DMM_AFFILIATE_ID'] ?? ($config['dmm']['affiliate_id'] ?? null);
if ($run && $dmmId && $dmmAff) {
    // best-effort: perform simple GET calls and capture result summaries
    $executed = [];
    foreach ($pages as $p) {
        // Example DMM API URL - adjust as needed for real API
        $qs = http_build_query([
            'api_id' => $dmmId,
            'affiliate_id' => $dmmAff,
            'hits' => $p['hits'],
            'offset' => $p['offset'],
            'sort' => $settings['API_SORT'] ?? 'date'
        ]);
        $url = 'https://api.dmm.com/affiliate/v3/ItemList?'. $qs;
        // perform request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $executed[] = ['offset' => $p['offset'], 'hits' => $p['hits'], 'http' => $http, 'error' => $err, 'sample' => substr($res ?? '', 0, 1024)];
    }
    $response['executed'] = $executed;
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
