<?php
declare(strict_types=1);

// admin/api_settings.php
// Admin UI to view/update DMM API fetch settings and trigger manual fetch.
// Requires: src/bootstrap.php, src/DB.php, .env.php (ADMIN_TOKEN)

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/DB.php';

$config = require __DIR__ . '/../.env.php';
DB::init($config);
$pdo = DB::get();

// Simple auth: Authorization: Bearer <token>
$adminToken = $config['ADMIN_TOKEN'] ?? '';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$adminToken || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m) || $m[1] !== $adminToken) {
    http_response_code(401);
    die('Unauthorized');
}

// Helper function
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// Fetch current settings from api_settings table
$current = [
    'API_RUN_INTERVAL' => 3600,
    'API_FETCH_COUNT' => 100,
    'API_FETCH_TOTAL' => 100,
    'API_SORT' => '-date',
    'API_GTE_DATE' => '',
    'API_LTE_DATE' => '',
    'API_SITE' => 'FANZA',
    'API_SERVICE' => 'digital',
    'API_FLOOR' => 'videoa'
];

try {
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM api_settings');
    while ($row = $stmt->fetch()) {
        $current[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Table might not exist yet
}

$messages = [];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($current as $key => $val) {
        if (isset($_POST[$key])) {
            $newVal = trim($_POST[$key]);
            // Cap API_FETCH_COUNT at 100
            if ($key === 'API_FETCH_COUNT') {
                $newVal = min((int)$newVal, 100);
            }
            try {
                $stmt = $pdo->prepare('INSERT INTO api_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
                $stmt->execute([$key, $newVal, $newVal]);
                $current[$key] = $newVal;
            } catch (Exception $e) {
                $messages[] = ['type' => 'error', 'text' => 'Error saving ' . $key . ': ' . $e->getMessage()];
            }
        }
    }
    if (empty($messages)) {
        $messages[] = ['type' => 'success', 'text' => 'Settings saved successfully.'];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin API Settings - abnormal-dmm</title>
<style>
body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 5px; }
h1, h2 { color: #333; }
.field { margin-bottom: 15px; }
.field.small { max-width: 400px; }
label { display: block; font-weight: bold; margin-bottom: 5px; }
input[type="text"], input[type="number"], select { width: 100%; padding: 8px; box-sizing: border-box; }
.note { font-size: 0.9em; color: #666; margin-top: 3px; }
.actions { margin-top: 20px; display: flex; gap: 10px; align-items: center; }
.btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
.btn { background: #0073aa; color: #fff; }
.btn.secondary { background: #666; }
.btn:hover { opacity: 0.9; }
.msg { padding: 10px; margin-bottom: 10px; border-radius: 4px; }
.msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.msg.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.status-box { padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; white-space: pre-wrap; font-family: monospace; font-size: 0.9em; max-height: 400px; overflow-y: auto; }
hr { margin: 30px 0; border: none; border-top: 1px solid #ddd; }
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
          <option value="<?php echo $i; ?>" <?php echo (string)$current['API_RUN_INTERVAL'] === (string)$i ? 'selected' : ''; ?>> <?php echo ($i/3600); ?>h (<?php echo $i; ?>s)</option>
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
