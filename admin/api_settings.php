<?php
declare(strict_types=1);

// admin/api_settings.php
// Admin UI to view/update DMM API fetch settings and trigger manual fetch.
// Requires: src/bootstrap.php, src/DB.php, .env.php (ADMIN_TOKEN)

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/helpers.php';

$config = $GLOBALS['config'];
$pdo = $GLOBALS['pdo'];

// Simple auth: Authorization: Bearer token
$adminToken = $config['admin']['token'] ?? env('ADMIN_TOKEN', '');

// Check auth for form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($adminToken) || empty($_POST['_token']) || !hash_equals($adminToken, $_POST['_token'])) {
        http_response_code(403);
        die('Forbidden');
    }
}

// Initialize settings table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `api_settings` (
        `key` VARCHAR(64) PRIMARY KEY,
        `value` TEXT NULL,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log('Failed to create api_settings table: ' . $e->getMessage());
}

// Default settings
$defaults = [
    'API_RUN_INTERVAL' => '3600',
    'API_FETCH_COUNT' => '20',
    'API_FETCH_TOTAL' => '100',
    'API_SORT' => 'date',
    'API_GTE_DATE' => '',
    'API_LTE_DATE' => '',
    'API_SITE' => 'FANZA',
    'API_SERVICE' => 'digital',
    'API_FLOOR' => 'videoa',
];

// Load current settings from database
$current = [];
$stmt = $pdo->query("SELECT `key`, `value` FROM `api_settings`");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $current[$row['key']] = $row['value'];
}

// Merge with defaults
foreach ($defaults as $k => $v) {
    if (!isset($current[$k])) {
        $current[$k] = $v;
    }
}

$messages = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_token'])) {
    $updates = [
        'API_RUN_INTERVAL' => $_POST['API_RUN_INTERVAL'] ?? '3600',
        'API_FETCH_COUNT' => min(1000, max(1, (int)($_POST['API_FETCH_COUNT'] ?? 20))),
        'API_FETCH_TOTAL' => min(1000, max(1, (int)($_POST['API_FETCH_TOTAL'] ?? 100))),
        'API_SORT' => $_POST['API_SORT'] ?? 'date',
        'API_GTE_DATE' => $_POST['API_GTE_DATE'] ?? '',
        'API_LTE_DATE' => $_POST['API_LTE_DATE'] ?? '',
        'API_SITE' => $_POST['API_SITE'] ?? 'FANZA',
        'API_SERVICE' => $_POST['API_SERVICE'] ?? 'digital',
        'API_FLOOR' => $_POST['API_FLOOR'] ?? 'videoa',
    ];

    try {
        $stmt = $pdo->prepare("INSERT INTO `api_settings` (`key`, `value`, `updated_at`) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()");
        foreach ($updates as $k => $v) {
            $stmt->execute([$k, (string)$v]);
        }
        $messages[] = ['type' => 'success', 'text' => 'Settings saved successfully!'];
        $current = $updates;
    } catch (Exception $e) {
        $messages[] = ['type' => 'error', 'text' => 'Failed to save settings: ' . $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin API Settings - abnormal-dmm</title>
<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    margin: 0;
    padding: 20px;
    background: #f5f5f5;
    color: #333;
}
.container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
h1 {
    margin-top: 0;
    color: #2c3e50;
}
h2 {
    margin-top: 30px;
    color: #34495e;
}
.msg {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.msg.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}
.msg.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
.field {
    margin-bottom: 20px;
}
.field.small {
    max-width: 400px;
}
label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #555;
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
input[type="text"]:focus,
input[type="number"]:focus,
select:focus {
    outline: none;
    border-color: #3498db;
}
.note {
    font-size: 12px;
    color: #777;
    margin-top: 4px;
}
.actions {
    margin-top: 30px;
    display: flex;
    gap: 10px;
    align-items: center;
}
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.2s;
}
.btn:hover {
    opacity: 0.9;
}
.btn:not(.secondary) {
    background: #3498db;
    color: white;
}
.btn.secondary {
    background: #95a5a6;
    color: white;
}
input.small {
    max-width: 250px;
}
hr {
    border: none;
    border-top: 1px solid #eee;
    margin: 40px 0;
}
.status-box {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 400px;
    overflow-y: auto;
}
code {
    background: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 13px;
}
</style>
</head>
<body>
<div class="container">
  <h1>API Fetch Settings</h1>

  <?php foreach ($messages as $m): ?>
    <div class="msg <?php echo h($m['type']); ?>"><?php echo h($m['text']); ?></div>
  <?php endforeach; ?>

  <form method="post" id="settingsForm">
    <input type="hidden" name="_token" value="<?php echo h($adminToken); ?>">
    
    <div class="field small">
      <label for="API_RUN_INTERVAL">API_RUN_INTERVAL (seconds)</label>
      <select id="API_RUN_INTERVAL" name="API_RUN_INTERVAL">
        <?php foreach ([3600, 10800, 21600, 43200, 86400] as $i): ?>
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
      <input type="text" id="manualToken" placeholder="ADMIN_TOKEN (optional)" class="small" value="">
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
