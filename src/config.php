<?php
/**
 * src/config.php
 * Application configuration loaded from environment variables (with safe defaults).
 */

return [
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'video_store',
        'user' => getenv('DB_USER') ?: 'video_user',
        'password' => getenv('DB_PASSWORD') ?: 'videopass',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
    'dmm' => [
        'api_id' => getenv('DMM_API_ID') ?: '',
        'affiliate_id' => getenv('DMM_AFFILIATE_ID') ?: '',
        'endpoint' => getenv('DMM_API_ENDPOINT') ?: 'https://api.dmm.com/affiliate/v3/ItemList',
    ],
    'admin' => [
        'token' => getenv('ADMIN_TOKEN') ?: '',
    ],
];