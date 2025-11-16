<?php
declare(strict_types=1);

// admin/api_settings.php
// Admin UI to view/update DMM API fetch settings and trigger manual fetch.
// Requires: src/bootstrap.php (provides $pdo, $config)

$bootstrap = require_once __DIR__ . '/../src/bootstrap.php';
$pdo = $bootstrap['pdo'];
$config = $bootstrap['config'];

// Load helper functions (already loaded in bootstrap but ensure it's available)
require_once __DIR__ . '/../src/helpers.php';

// Simple auth: Authorization: Bearer <token>
$adminToken = $config['admin']['token'] ?? '';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$providedToken = '';
if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $m)) {
    $providedToken = trim($m[1]);
}

// If no token provided or mismatch, show 401
if (empty($adminToken) || empty($providedToken) || !hash_equals($adminToken, $providedToken)) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

// Read current settings from api_settings table
$stmt = $pdo->query("SELECT setting_key, setting_value FROM api_settings");
$rows = $stmt->fetchAll();
$current = [];
foreach ($rows as $row) {
    $current[$row['setting_key']] = $row['setting_value'];
}

// Set defaults if not present
$defaults = [
    'API_RUN_INTERVAL' => '3600',
    'API_FETCH_COUNT' => '100',
    'API_FETCH_TOTAL' => '100',
    'API_SORT' => 'date',
    'API_GTE_DATE' => '',
    'API_LTE_DATE' => '',
    'API_SITE' => 'DMM.com',
    'API_SERVICE' => 'digital',
    'API_FLOOR' => 'videoa',
];

foreach ($defaults as $k => $v) {
    if (!isset($current[$k])) {
        $current[$k] = $v;
    }
}

$messages = [];

// Handle POST: update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed = array_keys($defaults);
    foreach ($allowed as $key) {
        if (isset($_POST[$key])) {
            $value = trim((string)$_POST[$key]);
            
            // Special handling: cap API_FETCH_COUNT at 100
            if ($key === 'API_FETCH_COUNT') {
                $val = (int)$value;
                if ($val > 100) {
                    $val = 100;
                }
                $value = (string)$val;
            }
            
            // Upsert into DB
            $stmt = $pdo->prepare("
                INSERT INTO api_settings (setting_key, setting_value)
                VALUES (:key, :value)
                ON DUPLICATE KEY UPDATE setting_value = :value
            ");
            $stmt->execute(['key' => $key, 'value' => $value]);
            $current[$key] = $value;
        }
    }
    $messages[] = ['type' => 'success', 'text' => 'Settings saved successfully'];
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
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  max-width: 800px;
  margin: 2rem auto;
  padding: 0 1rem;
  background: #f5f5f5;
  color: #333;
}
.container {
  background: #fff;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
h1 {
  margin-top: 0;
  color: #2c3e50;
}
h2 {
  color: #34495e;
  border-bottom: 2px solid #3498db;
  padding-bottom: 0.5rem;
}
.msg {
  padding: 0.75rem 1rem;
  margin: 1rem 0;
  border-radius: 4px;
  border-left: 4px solid;
}
.msg.success {
  background: #d4edda;
  border-color: #28a745;
  color: #155724;
}
.msg.error {
  background: #f8d7da;
  border-color: #dc3545;
  color: #721c24;
}
.field {
  margin-bottom: 1.5rem;
}
.field.small {
  max-width: 500px;
}
label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #2c3e50;
}
input[type="text"],
input[type="number"],
select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 1rem;
  box-sizing: border-box;
}
input[type="text"]:focus,
input[type="number"]:focus,
select:focus {
  outline: none;
  border-color: #3498db;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}
.note {
  font-size: 0.875rem;
  color: #6c757d;
  margin-top: 0.25rem;
}
.actions {
  display: flex;
  gap: 1rem;
  align-items: center;
  margin-top: 2rem;
}
.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 4px;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s;
}
.btn:not(.secondary) {
  background: #3498db;
  color: white;
}
.btn:not(.secondary):hover {
  background: #2980b9;
}
.btn.secondary {
  background: #95a5a6;
  color: white;
}
.btn.secondary:hover {
  background: #7f8c8d;
}
.actions input[type="text"] {
  flex: 1;
  max-width: 300px;
}
hr {
  border: none;
  border-top: 1px solid #ddd;
  margin: 2rem 0;
}
.status-box {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 1rem;
  font-family: monospace;
  white-space: pre-wrap;
  max-height: 400px;
  overflow-y: auto;
}
code {
  background: #f8f9fa;
  padding: 0.2rem 0.4rem;
  border-radius: 3px;
  font-family: monospace;
  font-size: 0.875rem;
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
          <option value="<?php echo $i; ?>" <?php echo ((string)$current['API_RUN_INTERVAL'] === (string)$i) ? 'selected' : ''; ?>> <?php echo ($i/3600); ?>h (<?php echo $i; ?>s)</option>
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
  <div class="note">Manual fetch calls <code>/public/api/admin/fetch.php</code> via fetch and shows JSON response.</div>
</div>

<script src="/public/assets/js/admin.js"></script>
<script>
(function(){
  // expose needed values to admin.js
  window.__ADMIN_UI = {
    fetchEndpoint: '/public/api/admin/fetch.php',
    defaultToken: '<?php echo h($adminToken ?? ''); ?>'
  };
})();
</script>

</body>
</html>
