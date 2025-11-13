<?php
/**
 * Helper functions for the application
 */

/**
 * Send a JSON response with appropriate headers
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
 * Sanitize output to prevent XSS
 *
 * @param string $string String to sanitize
 * @return string Sanitized string
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
