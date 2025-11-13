<?php
/**
 * Bootstrap File
 * 
 * Loads environment variables and initializes database connection
 */

// Load environment variables from .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
            
            // Set environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}

// Load configuration
$config = require __DIR__ . '/config.php';

// Create PDO database connection
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
    // In production, log the error instead of displaying it
    die('Database connection failed: ' . $e->getMessage());
}

// Return both PDO connection and config for use in application
return [
    'pdo' => $pdo,
    'config' => $config
];
