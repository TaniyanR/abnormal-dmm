<?php
declare(strict_types=1);

// admin/api_settings.php
// Admin UI to view/update DMM API fetch settings and trigger manual fetch.
// Requires: src/bootstrap.php, .env configuration

require_once __DIR__ . '/../src/bootstrap.php';

$container = require __DIR__ . '/../src/bootstrap.php';
$pdo = $container['pdo'];
$config = $container['config'];

// Simple auth: Authorization: Bearer <token>
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $token = trim($m[1]);
}
$adminToken = $config['admin']['token'] ?? '';
if (empty($adminToken) || $token !== $adminToken) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Helper: HTML escape
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// Read current settings from api_settings table
$stmt = $pdo->query("SELECT setting_key, setting_value FROM api_settings");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$current = [];
foreach ($rows as $row) {
    $current[$row['setting_key']] = $row['setting_value'];
}

// Defaults if not set
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
foreach ($defaults as $k => $v) {
    if (!isset($current[$k])) {
        $current[$k] = $v;
    }
}

$messages = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update settings
    $keys = ['API_RUN_INTERVAL', 'API_FETCH_COUNT', 'API_FETCH_TOTAL', 'API_SORT', 'API_GTE_DATE', 'API_LTE_DATE', 'API_SITE', 'API_SERVICE', 'API_FLOOR'];
    foreach ($keys as $key) {
        $val = $_POST[$key] ?? '';
        // Cap API_FETCH_COUNT at 100 for storage
        if ($key === 'API_FETCH_COUNT') {
            $val = (string)min((int)$val, 100);
        }
        $stmt = $pdo->prepare("INSERT INTO api_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        $stmt->execute([$key, $val]);
        $current[$key] = $val;
    }
    $messages[] = ['type' => 'success', 'text' => 'Settings saved successfully.'];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin API Settings - abnormal-dmm</title>
<style>
body { font-family: sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
.container { max-width: 800px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { margin-top: 0; }
h2 { margin-top: 30px; }
.field { margin-bottom: 15px; }
.field.small input, .field.small select { max-width: 300px; }
label { display: block; font-weight: bold; margin-bottom: 4px; }
input[type="text"], input[type="number"], select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
.note { font-size: 0.9em; color: #666; margin-top: 4px; }
.actions { margin-top: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
.btn { background: #007bff; color: #fff; }
.btn:hover { background: #0056b3; }
.btn.secondary { background: #6c757d; }
.btn.secondary:hover { background: #5a6268; }
.msg { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
.msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.msg.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.status-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; word-break: break-all; max-height: 400px; overflow-y: auto; }
hr { margin: 30px 0; border: none; border-top: 1px solid #dee2e6; }
input.small { max-width: 300px; }
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
          <option value="<?php echo $i; ?>" <?php echo ((string)$current['API_RUN_INTERVAL'] === (string)$i) ? 'selected' : ''; ?>>(<?php echo ($i/3600); ?>h)</option>
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
