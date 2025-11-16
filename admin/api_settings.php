<?php
declare(strict_types=1);

/**
 * admin/api_settings.php
 * Admin UI to view/update DMM API fetch settings and trigger manual fetch.
 * Requires: src/bootstrap.php, src/helpers.php
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/helpers.php';

$pdo = $GLOBALS['pdo'];
$config = $GLOBALS['config'];
$adminToken = $config['admin']['token'] ?? '';

// Simple auth check for page access
$authValid = false;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!empty($authHeader) && preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $token = trim($m[1]);
    if (!empty($adminToken) && hash_equals($adminToken, $token)) {
        $authValid = true;
    }
}

// Also check query param for convenience
if (!$authValid && isset($_GET['token'])) {
    $token = trim($_GET['token']);
    if (!empty($adminToken) && hash_equals($adminToken, $token)) {
        $authValid = true;
    }
}

if (!$authValid && !empty($adminToken)) {
    http_response_code(401);
    echo "<!DOCTYPE html><html><head><title>Unauthorized</title></head><body>";
    echo "<h1>401 Unauthorized</h1>";
    echo "<p>Authorization: Bearer &lt;token&gt; required or ?token=&lt;token&gt;</p>";
    echo "</body></html>";
    exit;
}

// Settings file path
$settingsFile = __DIR__ . '/../.api_settings.json';

// Default settings
$defaultSettings = [
    'API_RUN_INTERVAL' => 3600,
    'API_FETCH_COUNT' => 20,
    'API_FETCH_TOTAL' => 100,
    'API_SORT' => 'date',
    'API_GTE_DATE' => '',
    'API_LTE_DATE' => '',
    'API_SITE' => 'FANZA',
    'API_SERVICE' => 'digital',
    'API_FLOOR' => 'videoa',
];

// Load current settings
$current = $defaultSettings;
if (file_exists($settingsFile)) {
    $saved = json_decode(file_get_contents($settingsFile), true);
    if (is_array($saved)) {
        $current = array_merge($defaultSettings, $saved);
    }
}

// Handle form submission
$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['API_RUN_INTERVAL'])) {
    $newSettings = [
        'API_RUN_INTERVAL' => (int)$_POST['API_RUN_INTERVAL'],
        'API_FETCH_COUNT' => min(100, max(1, (int)$_POST['API_FETCH_COUNT'])),
        'API_FETCH_TOTAL' => (int)$_POST['API_FETCH_TOTAL'],
        'API_SORT' => trim($_POST['API_SORT']),
        'API_GTE_DATE' => trim($_POST['API_GTE_DATE']),
        'API_LTE_DATE' => trim($_POST['API_LTE_DATE']),
        'API_SITE' => trim($_POST['API_SITE']),
        'API_SERVICE' => trim($_POST['API_SERVICE']),
        'API_FLOOR' => trim($_POST['API_FLOOR']),
    ];
    
    if (file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT))) {
        $current = $newSettings;
        $messages[] = ['type' => 'success', 'text' => 'Settings saved successfully!'];
    } else {
        $messages[] = ['type' => 'error', 'text' => 'Failed to save settings.'];
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
  max-width: 800px;
  margin: 40px auto;
  padding: 0 20px;
  background: #f5f5f5;
  color: #333;
}
.container {
  background: #fff;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
h1 {
  color: #2c3e50;
  margin-top: 0;
}
h2 {
  color: #34495e;
  margin-top: 30px;
  border-bottom: 2px solid #3498db;
  padding-bottom: 10px;
}
.msg {
  padding: 12px 16px;
  margin: 10px 0;
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
  margin-bottom: 20px;
}
.field.small {
  max-width: 400px;
}
label {
  display: block;
  font-weight: 600;
  margin-bottom: 5px;
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
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
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
  flex-wrap: wrap;
}
.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s;
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
input.small {
  max-width: 300px;
}
hr {
  margin: 30px 0;
  border: none;
  border-top: 1px solid #ddd;
}
.status-box {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 15px;
  font-family: monospace;
  font-size: 13px;
  white-space: pre-wrap;
  word-wrap: break-word;
  max-height: 400px;
  overflow-y: auto;
}
code {
  background: #f4f4f4;
  padding: 2px 6px;
  border-radius: 3px;
  font-family: monospace;
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
        <?php foreach ([3600, 10800, 21600, 43200, 86400] as $i): ?>
          <option value="<?php echo $i; ?>" <?php echo ((string)$current['API_RUN_INTERVAL'] === (string)$i) ? 'selected' : ''; ?>>
            <?= $i; ?> (<?php echo ($i/3600); ?>h)
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
  <div class="note">Manual fetch calls <code>/api/admin/fetch</code> via fetch API and shows JSON response.</div>
</div>

<script src="/assets/js/admin.js"></script>
<script>
(function(){
  // Expose needed values to admin.js
  window.__ADMIN_UI = {
    fetchEndpoint: '/api/admin/fetch',
    defaultToken: '<?php echo h($adminToken ?? ''); ?>'
  };
})();
</script>

</body>
</html>
