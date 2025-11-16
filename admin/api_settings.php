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

// Simple auth: Authorization: Bearer <token> or X-Admin-Token header
$adminToken = $config['admin']['token'] ?? env('ADMIN_TOKEN', '');
$requestToken = '';

if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $requestToken = trim($m[1]);
    }
} elseif (!empty($_SERVER['HTTP_X_ADMIN_TOKEN'])) {
    $requestToken = trim($_SERVER['HTTP_X_ADMIN_TOKEN']);
}

// For browser access, we'll be more lenient - just show a warning
$isAuthorized = (!empty($adminToken) && !empty($requestToken) && hash_equals($adminToken, $requestToken));

$messages = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAuthorized) {
    $updates = [];
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
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $value = trim($_POST[$field]);
            
            // Cap API_FETCH_COUNT at 100 as mentioned
            if ($field === 'API_FETCH_COUNT') {
                $value = min(100, max(1, (int)$value));
            }
            
            $updates[$field] = $value;
        }
    }
    
    if (!empty($updates)) {
        try {
            // Store in a simple key-value settings table (assumes it exists)
            foreach ($updates as $key => $val) {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (key_name, value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE value = VALUES(value)
                ");
                $stmt->execute([$key, $val]);
            }
            $messages[] = ['type' => 'success', 'text' => 'Settings saved successfully'];
        } catch (Exception $e) {
            $messages[] = ['type' => 'error', 'text' => 'Failed to save settings: ' . $e->getMessage()];
        }
    }
}

// Load current settings
$current = [
    'API_RUN_INTERVAL' => '3600',
    'API_FETCH_COUNT' => '100',
    'API_FETCH_TOTAL' => '100',
    'API_SORT' => 'date',
    'API_GTE_DATE' => '',
    'API_LTE_DATE' => '',
    'API_SITE' => 'FANZA',
    'API_SERVICE' => 'digital',
    'API_FLOOR' => 'videoa'
];

try {
    $stmt = $pdo->query("SELECT key_name, value FROM settings");
    while ($row = $stmt->fetch()) {
        $current[$row['key_name']] = $row['value'];
    }
} catch (Exception $e) {
    // Settings table may not exist yet, use defaults
    $messages[] = ['type' => 'warning', 'text' => 'Using default settings (settings table not found)'];
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin API Settings - abnormal-dmm</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { font-size: 24px; margin-bottom: 20px; color: #333; }
h2 { font-size: 20px; margin: 30px 0 15px; color: #555; }
.msg { padding: 12px; margin-bottom: 15px; border-radius: 4px; }
.msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.msg.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.msg.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
.field { margin-bottom: 20px; }
.field.small { max-width: 500px; }
label { display: block; font-weight: 600; margin-bottom: 5px; color: #444; font-size: 14px; }
input[type="text"], input[type="number"], select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
input[type="text"]:focus, input[type="number"]:focus, select:focus { outline: none; border-color: #007bff; }
.note { font-size: 12px; color: #666; margin-top: 5px; }
.actions { display: flex; gap: 10px; align-items: center; margin-top: 25px; flex-wrap: wrap; }
.btn { padding: 10px 20px; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; font-weight: 600; }
.btn:hover { opacity: 0.9; }
button[type="submit"] { background: #007bff; color: white; }
.btn.secondary { background: #6c757d; color: white; }
input.small { max-width: 300px; }
hr { border: none; border-top: 1px solid #ddd; margin: 30px 0; }
.status-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; white-space: pre-wrap; max-height: 400px; overflow-y: auto; }
code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
.auth-warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 20px; color: #856404; }
</style>
</head>
<body>

<div class="container">
  <h1>API Fetch Settings</h1>

  <?php if (!$isAuthorized): ?>
    <div class="auth-warning">
      ⚠️ <strong>Not authenticated.</strong> Provide Authorization: Bearer &lt;ADMIN_TOKEN&gt; header to save settings.
    </div>
  <?php endif; ?>

  <?php foreach ($messages as $m): ?>
    <div class="msg <?php echo h($m['type']); ?>"><?= h($m['text']); ?></div>
  <?php endforeach; ?>

  <form method="post" id="settingsForm">
    <div class="field small">
      <label for="API_RUN_INTERVAL">API_RUN_INTERVAL (seconds)</label>
      <select id="API_RUN_INTERVAL" name="API_RUN_INTERVAL">
        <?php foreach ([3600,10800,21600,43200,86400] as $i): ?>
          <option value="<?php echo $i; ?>" <?php echo ((string)$current['API_RUN_INTERVAL'] === (string)$i) ? 'selected' : ''; ?>>
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
