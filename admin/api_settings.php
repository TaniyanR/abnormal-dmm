<?php
declare(strict_types=1);

// admin/api_settings.php
// Admin UI to view/update DMM API fetch settings and trigger manual fetch.
// Requires: src/bootstrap.php, src/helpers.php

$container = require __DIR__ . '/../src/bootstrap.php';
$pdo = $container['pdo'];
$config = $container['config'];

require_once __DIR__ . '/../src/helpers.php';

// Simple auth: Authorization: Bearer <token>
$adminToken = $config['admin']['token'] ?? getenv('ADMIN_TOKEN') ?: '';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$providedToken = '';
if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $providedToken = trim($m[1]);
}

if (empty($adminToken) || $providedToken !== $adminToken) {
    http_response_code(401);
    echo '<h1>401 Unauthorized</h1><p>Valid Bearer token required.</p>';
    exit;
}

// Helper function
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$messages = [];

// Handle POST updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
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
    
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        // API_FETCH_COUNT capped at 100 for storage
        if ($f === 'API_FETCH_COUNT') {
            $val = (string)min(100, max(1, (int)$val));
        }
        
        // Update or insert
        $stmt = $pdo->prepare("INSERT INTO api_settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$f, $val]);
    }
    $messages[] = ['type' => 'success', 'text' => 'Settings saved successfully!'];
}

// Load current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM api_settings");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$current = [];
foreach ($rows as $r) {
    $current[$r['setting_key']] = $r['setting_value'];
}

// Defaults
$current['API_RUN_INTERVAL'] = $current['API_RUN_INTERVAL'] ?? '3600';
$current['API_FETCH_COUNT'] = $current['API_FETCH_COUNT'] ?? '20';
$current['API_FETCH_TOTAL'] = $current['API_FETCH_TOTAL'] ?? '100';
$current['API_SORT'] = $current['API_SORT'] ?? '-date';
$current['API_GTE_DATE'] = $current['API_GTE_DATE'] ?? '';
$current['API_LTE_DATE'] = $current['API_LTE_DATE'] ?? '';
$current['API_SITE'] = $current['API_SITE'] ?? 'FANZA';
$current['API_SERVICE'] = $current['API_SERVICE'] ?? 'digital';
$current['API_FLOOR'] = $current['API_FLOOR'] ?? 'videoa';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin API Settings - abnormal-dmm</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: #f5f5f5;
  padding: 2rem;
  line-height: 1.6;
}
.container {
  max-width: 800px;
  margin: 0 auto;
  background: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
h1 { margin-bottom: 1.5rem; color: #333; }
h2 { margin-top: 2rem; margin-bottom: 1rem; color: #555; }
.msg {
  padding: 1rem;
  margin-bottom: 1rem;
  border-radius: 4px;
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
  color: #555;
}
input[type="text"],
input[type="number"],
select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 1rem;
}
input[type="text"].small,
input[type="number"].small {
  max-width: 300px;
}
.note {
  font-size: 0.875rem;
  color: #777;
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
  background: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 500;
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
hr {
  margin: 2rem 0;
  border: none;
  border-top: 1px solid #ddd;
}
.status-box {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 1rem;
  font-family: monospace;
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 400px;
  overflow-y: auto;
}
code {
  background: #f1f1f1;
  padding: 0.2rem 0.4rem;
  border-radius: 3px;
  font-size: 0.9rem;
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
            <?php echo $i; ?> (<?php echo ($i/3600); ?>h)</option>
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
