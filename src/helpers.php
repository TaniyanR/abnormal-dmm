<?php
/**
 * Helper Functions
 * 
 * Common utility functions used throughout the application
 */

/**
 * Send JSON response with appropriate headers
 * 
 * @param mixed $data Data to encode as JSON
 * @param int $statusCode HTTP status code (default: 200)
 * @return void
 */
function respondJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Sanitize string for safe output
 * 
 * @param string $str String to sanitize
 * @return string Sanitized string
 */
function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Get environment variable with optional default value
 * 
 * @param string $key Environment variable name
 * @param mixed $default Default value if not found
 * @return mixed Environment variable value or default
 */
function env($key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
