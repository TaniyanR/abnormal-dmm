<?php
/**
 * Configuration loader
 * Loads environment variables from .env file
 */

function loadEnv(string $filePath): void
{
    if (!file_exists($filePath)) {
        throw new RuntimeException(".env file not found at: {$filePath}");
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            // Set as environment variable if not already set
            if (!getenv($key)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

/**
 * Get environment variable with optional default
 */
function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}

// Load .env file from project root
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    loadEnv($envPath);
}

// Database configuration
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'video_store'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASSWORD', env('DB_PASSWORD', ''));
define('DB_CHARSET', 'utf8mb4');

// DMM API configuration
define('DMM_API_ID', env('DMM_API_ID', ''));
define('DMM_AFFILIATE_ID', env('DMM_AFFILIATE_ID', ''));
define('DMM_API_ENDPOINT', env('DMM_API_ENDPOINT', 'https://api.dmm.com/affiliate/v3/ItemList'));

// Admin configuration
define('ADMIN_TOKEN', env('ADMIN_TOKEN', ''));

// Application settings
define('APP_ENV', env('APP_ENV', 'development'));
define('APP_DEBUG', env('APP_DEBUG', 'true') === 'true');
define('TIMEZONE', env('TIMEZONE', 'Asia/Tokyo'));

// Set timezone
date_default_timezone_set(TIMEZONE);
