<?php
/**
 * Helper functions for the application
 */

/**
 * Send JSON response
 *
 * @param mixed $data Data to encode as JSON
 * @param int $statusCode HTTP status code
 * @param array $headers Additional headers
 */
function respondJson($data, int $statusCode = 200, array $headers = []): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    
    foreach ($headers as $key => $value) {
        header("{$key}: {$value}");
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Validate admin token
 *
 * @return bool True if valid, false otherwise
 */
function validateAdminToken(): bool
{
    $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $_GET['token'] ?? null;
    
    if (empty(ADMIN_TOKEN)) {
        return false; // No token configured
    }
    
    return hash_equals(ADMIN_TOKEN, (string)$token);
}

/**
 * Sanitize string for output
 *
 * @param string $string Input string
 * @return string Sanitized string
 */
function sanitize(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Log error message
 *
 * @param string $message Error message
 * @param array $context Additional context
 */
function logError(string $message, array $context = []): void
{
    $logMessage = sprintf(
        "[%s] %s %s\n",
        date('Y-m-d H:i:s'),
        $message,
        !empty($context) ? json_encode($context) : ''
    );
    
    error_log($logMessage);
}
