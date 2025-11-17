<?php
// test_db.php - 接続テスト用スクリプト（プロジェクトルートに置いてブラウザで開く）
$configPath = __DIR__ . '/.env.php';
if (!file_exists($configPath)) {
    echo ".env.php not found in project root. Create .env.php first.\n";
    exit;
}
$config = require $configPath;

$host = $config['DB_HOST'] ?? '127.0.0.1';
$port = $config['DB_PORT'] ?? 3306;
$db   = $config['DB_NAME'] ?? '';
$user = $config['DB_USER'] ?? '';
$pass = $config['DB_PASS'] ?? '';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "DB OK\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_NUM);
    if (count($tables) === 0) {
        echo "No tables found.\n";
    } else {
        echo "Tables:\n";
        foreach ($tables as $t) echo "- " . $t[0] . "\n";
    }
} catch (Throwable $e) {
    echo "Connection failed: " . $e->getMessage();
}
