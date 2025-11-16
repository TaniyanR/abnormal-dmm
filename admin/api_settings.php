<?php
declare(strict_types=1);

// admin/api_settings.php
// Admin UI to view/update DMM API fetch settings and trigger manual fetch.
// Requires: src/bootstrap.php, src/DB.php, .env.php (ADMIN_TOKEN)

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/helpers.php';

$config = $GLOBALS['config'] ?? [];
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    die('Database connection not available');
}

// Simple auth: Authorization: Bearer <token>
$adminToken = $config['admin']['token'] ?? getenv('ADMIN_TOKEN') ?: '';
$authorized = false;

// Check Authorization header
if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $token = trim($m[1]);
        if ($adminToken && hash_equals($adminToken, $token)) {
            $authorized = true;
        }
    }
}

// Also allow cookie-based simple session for web UI
if (!$authorized && !empty($_COOKIE['admin_session'])) {
    if ($adminToken && hash_equals($adminToken, $_COOKIE['admin_session'])) {
        $authorized = true;
    }
}

if (!$authorized) {
    http_response_code(401);
    die('Unauthorized. Please provide valid admin token.');
}

// Handle form submission (update settings)
$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['_fetch_trigger'])) {
    // Create settings table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `api_settings` (
            `key` VARCHAR(64) PRIMARY KEY,
            `value` TEXT,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $fieldsToSave = [
        'API_RUN_INTERVAL',
        'API_FETCH_COUNT',
        'API_FETCH_TOTAL',
        'API_SORT',
        'API_GTE_DATE',
        'API_LTE_DATE',
        'API_SITE',
        'API_SERVICE',
        'API_FLOOR'
    ];
    
    foreach ($fieldsToSave as $field) {
        if (isset($_POST[$field])) {
            $value = trim($_POST[$field]);
            
            // Special handling for API_FETCH_COUNT - cap at 100
            if ($field === 'API_FETCH_COUNT') {
                $value = (string)min(100, max(1, (int)$value));
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO api_settings (`key`, `value`) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
            ");
            $stmt->execute([$field, $value]);
        }
    }
    
    $messages[] = ['type' => 'success', 'text' => 'Settings saved successfully!'];
}

// Fetch current settings
$current = [
    'API_RUN_INTERVAL' => '3600',
    'API_FETCH_COUNT' => '20',
    'API_FETCH_TOTAL' => '100',
    'API_SORT' => 'date',
    'API_GTE_DATE' => '',
    'API_LTE_DATE' => '',
    'API_SITE' => 'FANZA',
    'API_SERVICE' => 'digital',
    'API_FLOOR' => 'videoa'
];

try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM api_settings");
    while ($row = $stmt->fetch()) {
        $current[$row['key']] = $row['value'];
    }
} catch (Exception $e) {
    // Table doesn't exist yet, use defaults
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin API Settings - abnormal-dmm</title>
<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
    background: #f5f5f5;
}
.container {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
h1 {
    margin-top: 0;
    color: #333;
}
h2 {
    color: #555;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
    margin-top: 30px;
}
.field {
    margin-bottom: 20px;
}
.field.small {
    max-width: 500px;
}
label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
}
input[type="text"],
input[type="number"],
select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}
.note {
    font-size: 12px;
    color: #666;
    margin-top: 4px;
}
.actions {
    margin-top: 30px;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    background: #007bff;
    color: white;
    transition: background 0.2s;
}
.btn:hover {
    background: #0056b3;
}
.btn.secondary {
    background: #6c757d;
}
.btn.secondary:hover {
    background: #545b62;
}
.msg {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.msg.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.msg.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.status-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 16px;
    font-family: monospace;
    font-size: 13px;
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 400px;
    overflow-y: auto;
}
hr {
    border: none;
    border-top: 1px solid #eee;
    margin: 30px 0;
}
</style>
</head>
<body>
<div class="container">
  <h1>API Fetch Settings</h1>

  <?php foreach ($messages as $m): ?>
    <div class="msg <?php echo h($m['type']); ?>"><?= h($m['text']); ?></div>
  <?php endforeach; ?>

  <form method="post" id="settingsForm">
    <div class="field small">
      <label for="API_RUN_INTERVAL">API_RUN_INTERVAL (seconds)</label>
      <select id="API_RUN_INTERVAL" name="API_RUN_INTERVAL">
        <?php foreach ([3600,10800,21600,43200,86400] as $i): ?>
          <option value="<?php echo $i; ?>" <?php echo (string)$current['API_RUN_INTERVAL'] === (string)$i ? 'selected' : ''; ?>>
            <?php echo $i; ?> (<?php echo ($i/3600); ?>h)
          </option>
        <?php endforeach; ?>
      </select>
      <div class="note">取得間隔（秒）。例: 3600 = 1h</div>
    </div>

    <div class="field small">
      <label for="API_FETCH_COUNT">API_FETCH_COUNT (per request, stored capped at 100)</label>
      <input type="number" id="API_FETCH_COUNT" name="API_FETCH_COUNT" min="1" max="1000" value="<?php echo h($current['API_FETCH_COUNT']); ?>">
      <div class="note">DMM API の hits。保存時は最大 100 に制限されます。</div>
    </div>

    <div class="field small">
      <label for="API_FETCH_TOTAL">API_FETCH_TOTAL (total items to fetch)</label>
      <input type="number" id="API_FETCH_TOTAL" name="API_FETCH_TOTAL" min="1" max="1000" value="<?php echo h($current['API_FETCH_TOTAL']); ?>">
      <div class="note">管理画面でまとめて取得したい合計件数（offset をずらして複数リクエストします）</div>
    </div>

    <div class="field small">
      <label for="API_SORT">API_SORT</label>
      <input type="text" id="API_SORT" name="API_SORT" value="<?php echo h($current['API_SORT']); ?>">
    </div>

    <div class="field small">
      <label for="API_GTE_DATE">API_GTE_DATE (optional)</label>
      <input type="text" id="API_GTE_DATE" name="API_GTE_DATE" placeholder="YYYY-MM-DD" value="<?php echo h($current['API_GTE_DATE']); ?>">
    </div>

    <div class="field small">
      <label for="API_LTE_DATE">API_LTE_DATE (optional)</label>
      <input type="text" id="API_LTE_DATE" name="API_LTE_DATE" placeholder="YYYY-MM-DD" value="<?php echo h($current['API_LTE_DATE']); ?>">
    </div>

    <div class="field small">
      <label for="API_SITE">API_SITE</label>
      <input type="text" id="API_SITE" name="API_SITE" value="<?php echo h($current['API_SITE']); ?>">
    </div>

    <div class="field small">
      <label for="API_SERVICE">API_SERVICE</label>
      <input type="text" id="API_SERVICE" name="API_SERVICE" value="<?php echo h($current['API_SERVICE']); ?>">
    </div>

    <div class="field small">
      <label for="API_FLOOR">API_FLOOR</label>
      <input type="text" id="API_FLOOR" name="API_FLOOR" value="<?php echo h($current['API_FLOOR']); ?>">
    </div>

    <div class="actions">
      <button type="submit" class="btn">Save Settings</button>
      <button type="button" id="runFetchBtn" class="btn secondary">Run manual fetch</button>
      <input type="text" id="manualToken" placeholder="ADMIN_TOKEN (optional)" class="small" value="" style="width: 250px;">
    </div>
  </form>

  <hr>

  <h2>Manual Fetch Output</h2>
  <div id="fetchResult" class="status-box">Not run yet.</div>
  <div class="note">Manual fetch calls <code>/api/admin/fetch</code> via fetch and shows JSON response.</div>
</div>

<script src="/public/assets/js/admin.js"></script>
<script>
(function(){
  // expose needed values to admin.js
  window.__ADMIN_UI = {
    fetchEndpoint: '/api/admin/fetch',
    defaultToken: '<?php echo h($adminToken ?? ''); ?>'
  };
})();
</script>
</body>
</html>
