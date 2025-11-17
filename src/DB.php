<?php
/**
 * src/DB.php
 * Simple database wrapper/singleton
 */

class DB {
    private static $pdo = null;
    
    public static function init(array $config) {
        if (self::$pdo !== null) {
            return;
        }
        
        $dbConfig = $config['database'] ?? $config;
        
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $dbConfig['host'] ?? $dbConfig['DB_HOST'] ?? '127.0.0.1',
            $dbConfig['port'] ?? $dbConfig['DB_PORT'] ?? '3306',
            $dbConfig['name'] ?? $dbConfig['DB_NAME'] ?? 'video_store',
            $dbConfig['charset'] ?? $dbConfig['DB_CHARSET'] ?? 'utf8mb4'
        );
        
        $user = $dbConfig['user'] ?? $dbConfig['DB_USER'] ?? 'video_user';
        $password = $dbConfig['password'] ?? $dbConfig['DB_PASSWORD'] ?? '';
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        self::$pdo = new PDO($dsn, $user, $password, $options);
    }
    
    public static function get(): PDO {
        if (self::$pdo === null) {
            throw new RuntimeException('DB not initialized. Call DB::init() first.');
        }
        return self::$pdo;
    }
}
