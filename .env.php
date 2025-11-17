<?php
/**
 * .env.php
 * Returns configuration array from environment variables
 */

return [
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'video_store',
        'user' => getenv('DB_USER') ?: 'video_user',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    'ADMIN_TOKEN' => getenv('ADMIN_TOKEN') ?: '',
    'DMM_API_ID' => getenv('DMM_API_ID') ?: '',
    'DMM_AFFILIATE_ID' => getenv('DMM_AFFILIATE_ID') ?: '',
];
