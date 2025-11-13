<?php
/**
 * src/helpers.php
 * Common helper functions used across the application.
 */

/**
 * Send JSON response with appropriate headers and terminate.
 *
 * @param mixed $data Data to encode as JSON
 * @param int $statusCode HTTP status code (default: 200)
 * @param array $headers Additional headers to send (key => value)
 * @return void
 */
function respondJson($data, $statusCode = 200, array $headers = []) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    foreach ($headers as $key => $value) {
        header("{$key}: {$value}");
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Get environment variable with optional default.
 *
 * @param string $key Environment variable name
 * @param mixed $default Default value if not found
 * @return mixed Environment variable value or default
 */
function env($key, $default = null) {
    $value = getenv($key);
    return ($value !== false) ? $value : $default;
}

/**
 * Sanitize string for safe HTML output.
 *
 * Alias `h()` kept for convenience (common in PHP templates).
 *
 * @param string $str String to sanitize
 * @return string Sanitized string
 */
function sanitize($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function h($str) {
    return sanitize($str);
}

/**
 * Validate admin token provided via X-Admin-Token header, Authorization Bearer header, or ?token= query.
 *
 * @return bool True if valid, false otherwise
 */
function validateAdminToken(): bool
{
    // Check X-Admin-Token header first
    $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;

    // If not provided, check Authorization: Bearer <token>
    if (empty($token) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = trim($m[1]);
        }
    }

    // As a fallback, allow token via query param (useful for curl/testing)
    if (empty($token) && isset($_GET['token'])) {
        $token = (string)$_GET['token'];
    }

    $expected = env('ADMIN_TOKEN', '');

    if (empty($expected) || empty($token)) {
        return false;
    }

    // Use hash_equals to mitigate timing attacks
    return hash_equals((string)$expected, (string)$token);
}

/**
 * Log error message with optional context (uses PHP error_log).
 *
 * @param string $message Error message
 * @param array $context Additional context (will be JSON-encoded)
 * @return void
 */
function logError(string $message, array $context = []): void
{
    $logMessage = sprintf(
        "[%s] %s %s\n",
        date('Y-m-d H:i:s'),
        $message,
        !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
    );

    error_log($logMessage);
}