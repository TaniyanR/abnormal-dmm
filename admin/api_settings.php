<?php
/**
 * admin/api_settings.php
 * Admin UI page for managing DMM API fetch settings.
 * 
 * Features:
 * - View and update settings stored in `settings` table
 * - Trigger manual fetch via existing admin API endpoint
 * - Token-based authentication (Bearer token)
 */

// Bootstrap the application
$container = require __DIR__ . '/../src/bootstrap.php';
$pdo = $container['pdo'];
$config = $container['config'];

require_once __DIR__ . '/../src/helpers.php';

// Ensure settings table exists (create if not)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `settings` (
            `key` VARCHAR(191) NOT NULL,
            `value` TEXT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    error_log('Settings table creation error: ' . $e->getMessage());
    // Continue anyway - table might already exist
}

// Admin authentication
$isAuthenticated = false;
$adminToken = '';

// Check for Bearer token in Authorization header
if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $adminToken = trim($m[1]);
    }
}

// Check for session-based authentication (future enhancement)
// For now, we rely on token-based auth

$expectedToken = $config['admin']['token'] ?? getenv('ADMIN_TOKEN') ?: '';

if (!empty($expectedToken) && !empty($adminToken) && hash_equals($expectedToken, $adminToken)) {
    $isAuthenticated = true;
}

// For GET requests, we allow viewing with token in query param for convenience
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$isAuthenticated) {
    if (!empty($_GET['token']) && hash_equals($expectedToken, $_GET['token'])) {
        $isAuthenticated = true;
        $adminToken = $_GET['token'];
    }
}

// If not authenticated, show login form
if (!$isAuthenticated && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - API Settings</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
            }
            .login-container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.15);
                padding: 40px;
                width: 100%;
                max-width: 400px;
            }
            h1 {
                color: #333;
                font-size: 24px;
                margin-bottom: 8px;
                text-align: center;
            }
            .subtitle {
                color: #666;
                font-size: 14px;
                text-align: center;
                margin-bottom: 30px;
            }
            .form-group {
                margin-bottom: 20px;
            }
            label {
                display: block;
                color: #333;
                font-size: 14px;
                font-weight: 500;
                margin-bottom: 8px;
            }
            input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 14px;
                transition: border-color 0.3s;
            }
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }
            .btn-primary {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: opacity 0.3s;
            }
            .btn-primary:hover {
                opacity: 0.9;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>🔐 Admin Login</h1>
            <p class="subtitle">API Settings Management</p>
            <form method="GET" action="">
                <div class="form-group">
                    <label for="token">Admin Token</label>
                    <input type="password" id="token" name="token" required placeholder="Enter your admin token">
                </div>
                <button type="submit" class="btn-primary">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle POST request to save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAuthenticated) {
    $errors = [];
    $success = false;
    
    // Validate inputs
    $interval = isset($_POST['API_RUN_INTERVAL']) ? (int)$_POST['API_RUN_INTERVAL'] : null;
    $fetchCount = isset($_POST['API_FETCH_COUNT']) ? (int)$_POST['API_FETCH_COUNT'] : null;
    $fetchTotal = isset($_POST['API_FETCH_TOTAL']) ? (int)$_POST['API_FETCH_TOTAL'] : null;
    $sort = isset($_POST['API_SORT']) ? trim($_POST['API_SORT']) : '';
    $gteDate = isset($_POST['API_GTE_DATE']) ? trim($_POST['API_GTE_DATE']) : '';
    $lteDate = isset($_POST['API_LTE_DATE']) ? trim($_POST['API_LTE_DATE']) : '';
    $site = isset($_POST['API_SITE']) ? trim($_POST['API_SITE']) : '';
    $service = isset($_POST['API_SERVICE']) ? trim($_POST['API_SERVICE']) : '';
    $floor = isset($_POST['API_FLOOR']) ? trim($_POST['API_FLOOR']) : '';
    
    // Validate interval
    $allowedIntervals = [3600, 10800, 21600, 43200, 86400];
    if ($interval !== null && !in_array($interval, $allowedIntervals)) {
        $errors[] = 'Invalid API_RUN_INTERVAL value';
    }
    
    // Validate counts
    if ($fetchCount !== null && ($fetchCount < 1 || $fetchCount > 1000)) {
        $errors[] = 'API_FETCH_COUNT must be between 1 and 1000';
    }
    if ($fetchTotal !== null && ($fetchTotal < 1 || $fetchTotal > 1000)) {
        $errors[] = 'API_FETCH_TOTAL must be between 1 and 1000';
    }
    
    // Cap fetch count at 100
    if ($fetchCount !== null && $fetchCount > 100) {
        $fetchCount = 100;
    }
    
    if (empty($errors)) {
        try {
            // Save settings using upsert logic
            $settings = [
                'API_RUN_INTERVAL' => $interval,
                'API_FETCH_COUNT' => $fetchCount,
                'API_FETCH_TOTAL' => $fetchTotal,
                'API_SORT' => $sort,
                'API_GTE_DATE' => $gteDate,
                'API_LTE_DATE' => $lteDate,
                'API_SITE' => $site,
                'API_SERVICE' => $service,
                'API_FLOOR' => $floor,
            ];
            
            foreach ($settings as $key => $value) {
                if ($value === null || $value === '') {
                    continue; // Skip empty values
                }
                
                // Try UPDATE first
                $stmt = $pdo->prepare('UPDATE settings SET value = ?, updated_at = NOW() WHERE `key` = ?');
                $stmt->execute([$value, $key]);
                
                // If no rows affected, INSERT
                if ($stmt->rowCount() === 0) {
                    $stmt = $pdo->prepare('INSERT INTO settings (`key`, value, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
                    $stmt->execute([$key, $value]);
                }
            }
            
            $success = true;
        } catch (PDOException $e) {
            error_log('Settings save error: ' . $e->getMessage());
            $errors[] = 'Database error occurred while saving settings';
        }
    }
    
    // Return JSON for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
        } else {
            echo json_encode(['success' => false, 'errors' => $errors]);
        }
        exit;
    }
}

// Fetch current settings from database
function getSetting($pdo, $key, $default = '') {
    static $cache = [];
    
    if (!isset($cache[$key])) {
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cache[$key] = $row ? $row['value'] : null;
    }
    
    return $cache[$key] !== null ? $cache[$key] : $default;
}

// Get settings with fallback to .env
$apiRunInterval = getSetting($pdo, 'API_RUN_INTERVAL', getenv('API_RUN_INTERVAL') ?: '3600');
$apiFetchCount = getSetting($pdo, 'API_FETCH_COUNT', getenv('API_FETCH_COUNT') ?: '20');
$apiFetchTotal = getSetting($pdo, 'API_FETCH_TOTAL', getenv('API_FETCH_TOTAL') ?: '100');
$apiSort = getSetting($pdo, 'API_SORT', getenv('API_SORT') ?: 'date');
$apiGteDate = getSetting($pdo, 'API_GTE_DATE', getenv('API_GTE_DATE') ?: '');
$apiLteDate = getSetting($pdo, 'API_LTE_DATE', getenv('API_LTE_DATE') ?: '');
$apiSite = getSetting($pdo, 'API_SITE', getenv('API_SITE') ?: 'FANZA');
$apiService = getSetting($pdo, 'API_SERVICE', getenv('API_SERVICE') ?: 'digital');
$apiFloor = getSetting($pdo, 'API_FLOOR', getenv('API_FLOOR') ?: 'videoa');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - API Settings</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: #333;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
        }
        label small {
            color: #999;
            font-weight: normal;
        }
        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 640px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        #fetchResponse {
            margin-top: 16px;
            padding: 12px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ API Settings Management</h1>
            <p class="subtitle">Configure DMM API fetch settings and trigger manual operations</p>
        </div>
        
        <?php if (isset($success) && $success): ?>
            <div class="alert alert-success">✅ Settings saved successfully!</div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>❌ Errors:</strong>
                <ul style="margin-top: 8px; margin-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>📊 Fetch Configuration</h2>
            <form method="POST" action="?token=<?= urlencode($adminToken) ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="API_RUN_INTERVAL">
                            Run Interval <small>(seconds)</small>
                        </label>
                        <select id="API_RUN_INTERVAL" name="API_RUN_INTERVAL">
                            <option value="3600" <?= $apiRunInterval == 3600 ? 'selected' : '' ?>>1 hour (3600s)</option>
                            <option value="10800" <?= $apiRunInterval == 10800 ? 'selected' : '' ?>>3 hours (10800s)</option>
                            <option value="21600" <?= $apiRunInterval == 21600 ? 'selected' : '' ?>>6 hours (21600s)</option>
                            <option value="43200" <?= $apiRunInterval == 43200 ? 'selected' : '' ?>>12 hours (43200s)</option>
                            <option value="86400" <?= $apiRunInterval == 86400 ? 'selected' : '' ?>>24 hours (86400s)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="API_FETCH_COUNT">
                            Fetch Count <small>(per request, max 100)</small>
                        </label>
                        <input type="number" id="API_FETCH_COUNT" name="API_FETCH_COUNT" 
                               value="<?= h($apiFetchCount) ?>" min="1" max="100">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="API_FETCH_TOTAL">
                            Total Fetch Limit <small>(1-1000)</small>
                        </label>
                        <input type="number" id="API_FETCH_TOTAL" name="API_FETCH_TOTAL" 
                               value="<?= h($apiFetchTotal) ?>" min="1" max="1000">
                    </div>
                    
                    <div class="form-group">
                        <label for="API_SORT">Sort Order</label>
                        <select id="API_SORT" name="API_SORT">
                            <option value="date" <?= $apiSort === 'date' ? 'selected' : '' ?>>Date (newest first)</option>
                            <option value="rank" <?= $apiSort === 'rank' ? 'selected' : '' ?>>Rank</option>
                            <option value="price" <?= $apiSort === 'price' ? 'selected' : '' ?>>Price</option>
                            <option value="review" <?= $apiSort === 'review' ? 'selected' : '' ?>>Review</option>
                        </select>
                    </div>
                </div>
                
                <h2 style="margin-top: 30px;">🔍 Filter Options</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="API_GTE_DATE">
                            Date From <small>(YYYY-MM-DD)</small>
                        </label>
                        <input type="text" id="API_GTE_DATE" name="API_GTE_DATE" 
                               value="<?= h($apiGteDate) ?>" placeholder="2024-01-01">
                    </div>
                    
                    <div class="form-group">
                        <label for="API_LTE_DATE">
                            Date To <small>(YYYY-MM-DD)</small>
                        </label>
                        <input type="text" id="API_LTE_DATE" name="API_LTE_DATE" 
                               value="<?= h($apiLteDate) ?>" placeholder="2024-12-31">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="API_SITE">Site</label>
                        <input type="text" id="API_SITE" name="API_SITE" 
                               value="<?= h($apiSite) ?>" placeholder="FANZA">
                    </div>
                    
                    <div class="form-group">
                        <label for="API_SERVICE">Service</label>
                        <input type="text" id="API_SERVICE" name="API_SERVICE" 
                               value="<?= h($apiService) ?>" placeholder="digital">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="API_FLOOR">Floor</label>
                    <input type="text" id="API_FLOOR" name="API_FLOOR" 
                           value="<?= h($apiFloor) ?>" placeholder="videoa">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">💾 Save Settings</button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2>🚀 Manual Operations</h2>
            <p style="color: #666; margin-bottom: 20px; font-size: 14px;">
                Trigger a manual fetch from the DMM API using current settings.
            </p>
            
            <button type="button" id="manualFetchBtn" class="btn btn-primary" onclick="triggerManualFetch()">
                ▶️ Run Manual Fetch
            </button>
            
            <div id="fetchResponse"></div>
        </div>
    </div>
    
    <script src="/public/assets/js/admin.js"></script>
    <script>
        // Pass admin token to JavaScript
        window.ADMIN_TOKEN = <?= json_encode($adminToken) ?>;
        window.API_ENDPOINT = '/api/admin/fetch';
    </script>
</body>
</html>
