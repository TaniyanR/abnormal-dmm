<?php
/**
 * Configuration file for database and application settings
 * This file provides access to environment-based configuration
 */

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'video_store',
        'user' => getenv('DB_USER') ?: 'dbuser',
        'password' => getenv('DB_PASSWORD') ?: 'dbpassword',
        'charset' => 'utf8mb4',
    ],
    'dmm' => [
        'api_id' => getenv('DMM_API_ID') ?: '',
        'affiliate_id' => getenv('DMM_AFFILIATE_ID') ?: '',
    ],
    'admin' => [
        'token' => getenv('ADMIN_TOKEN') ?: '',
    ],
];
