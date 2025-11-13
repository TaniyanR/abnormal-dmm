<?php
/**
 * Bootstrap file - Initialize database connection and dependencies
 */

require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/helpers.php';

/**
 * Create and return PDO database connection
 */
function getDbConnection(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                throw new RuntimeException("Database connection failed: " . $e->getMessage());
            }
            throw new RuntimeException("Database connection failed. Please check your configuration.");
        }
    }
    
    return $pdo;
}
