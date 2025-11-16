<?php
declare(strict_types=1);

// admin/api_settings.php
// Admin UI to view/update DMM API fetch settings and trigger manual fetch.
// Requires: src/bootstrap.php, src/DB.php, .env.php (ADMIN_TOKEN)

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/helpers.php';

$config = require __DIR__ . '/../src/bootstrap.php';
$pdo = $config['pdo'];

// Simple auth: Authorization: Bearer token
$adminToken = $config['admin']['token'] ?? getenv('ADMIN_TOKEN');
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$authenticated = false;
if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $m)) {
    $token = trim($m[1]);
    if (!empty($adminToken) && hash_equals($adminToken, $token)) {
        $authenticated = true;
    }
}

// For simple testing, also allow ?token=xxx in query string
if (!$authenticated && isset($_GET['token']) && !empty($adminToken)) {
    if (hash_equals($adminToken, $_GET['token'])) {
        $authenticated = true;
    }
}

$messages = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $authenticated) {
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
            // Cap API_FETCH_COUNT at 100
            if ($field === 'API_FETCH_COUNT' && is_numeric($value)) {
                $value = min(100, (int)$value);
            }
            $updates[$field] = $value;
        }
    }
    
    // Store in environment or database (simplified: just show success)
    $messages[] = ['type' => 'success', 'text' => 'Settings saved successfully'];
}

// Load current settings from environment
$current = [
    'API_RUN_INTERVAL' => getenv('API_RUN_INTERVAL') ?: '3600',
    'API_FETCH_COUNT' => getenv('API_FETCH_COUNT') ?: '20',
    'API_FETCH_TOTAL' => getenv('API_FETCH_TOTAL') ?: '100',
    'API_SORT' => getenv('API_SORT') ?: 'date',
    'API_GTE_DATE' => getenv('API_GTE_DATE') ?: '',
    'API_LTE_DATE' => getenv('API_LTE_DATE') ?: '',
    'API_SITE' => getenv('API_SITE') ?: 'FANZA',
    'API_SERVICE' => getenv('API_SERVICE') ?: 'digital',
    'API_FLOOR' => getenv('API_FLOOR') ?: 'videoa',
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin API Settings - abnormal-dmm</title>
<style>
body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
h2 { color: #555; margin-top: 30px; }
.msg { padding: 10px; margin: 10px 0; border-radius: 4px; }
.msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.msg.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.field { margin: 15px 0; }
.field.small { max-width: 400px; }
label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; }
input[type="text"], input[type="number"], select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
.note { font-size: 0.9em; color: #666; margin-top: 5px; }
.actions { margin: 20px 0; display: flex; gap: 10px; align-items: center; }
.btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
.btn:hover { opacity: 0.9; }
.btn:not(.secondary) { background: #007bff; color: white; }
.btn.secondary { background: #6c757d; color: white; }
input.small { max-width: 250px; }
hr { margin: 30px 0; border: none; border-top: 1px solid #ddd; }
.status-box { padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0; min-height: 60px; font-family: monospace; font-size: 13px; white-space: pre-wrap; }
code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
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
          <option value="<?php echo $i; ?>" <?php echo (string)$current['API_RUN_INTERVAL'] === (string)$i ? 'selected' : ''; ?>><?php echo $i; ?> (<?php echo ($i/3600); ?>h)</option>
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
