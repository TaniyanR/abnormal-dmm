<?php
/**
 * src/bootstrap.php
 * Load environment variables (from .env) and initialize a PDO connection.
 *
 * This file sets $pdo (global) and also returns ['pdo' => $pdo, 'config' => $config]
 * so it works whether included via `require_once` or via `$bootstrap = require`.
 */

// Load .env if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Strip surrounding quotes
        if (preg_match('/^([\'"])(.*)\1$/', $value, $m)) {
            $value = $m[2];
        }

        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Defaults
$defaults = [
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'video_store',
    'DB_USER' => 'video_user',
    'DB_PASSWORD' => 'videopass',
    'DB_CHARSET' => 'utf8mb4',
    'DMM_API_ID' => '',
    'DMM_AFFILIATE_ID' => '',
    'DMM_API_ENDPOINT' => 'https://api.dmm.com/affiliate/v3/ItemList',
    'ADMIN_TOKEN' => '',
    'APP_ENV' => 'development',
    'APP_DEBUG' => 'true',
];

foreach ($defaults as $k => $v) {
    if (getenv($k) === false) {
        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}

// Build config array (useful for code paths that expect config)
$config = [
    'database' => [
        'host' => getenv('DB_HOST'),
        'port' => getenv('DB_PORT'),
        'name' => getenv('DB_NAME'),
        'user' => getenv('DB_USER'),
        'password' => getenv('DB_PASSWORD'),
        'charset' => getenv('DB_CHARSET'),
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
    'dmm' => [
        'api_id' => getenv('DMM_API_ID'),
        'affiliate_id' => getenv('DMM_AFFILIATE_ID'),
        'endpoint' => getenv('DMM_API_ENDPOINT'),
    ],
    'admin' => [
        'token' => getenv('ADMIN_TOKEN'),
    ],
    'app' => [
        'env' => getenv('APP_ENV'),
        'debug' => filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN),
        'timezone' => getenv('TIMEZONE') ?: 'UTC',
    ],
];

// Create PDO connection
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['database']['host'],
        $config['database']['port'],
        $config['database']['name'],
        $config['database']['charset']
    );

    $pdo = new PDO(
        $dsn,
        $config['database']['user'],
        $config['database']['password'],
        $config['database']['options']
    );
} catch (PDOException $e) {
    // For web UI during development, log and output minimal message.
    // Do not leak credentials or long stack traces.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Export globals for simple require usage
$GLOBALS['pdo'] = $pdo;
$GLOBALS['config'] = $config;

// Also return for require-as-expression usage
return [
    'pdo' => $pdo,
    'config' => $config
];