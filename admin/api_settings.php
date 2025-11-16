<?php
declare(strict_types=1);

/**
 * admin/api_settings.php
 * Admin UI to view/update DMM API fetch settings and trigger manual fetch.
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/helpers.php';

// Get config from bootstrap
$config = $GLOBALS['config'] ?? [];
$pdo = $GLOBALS['pdo'] ?? null;

// Simple auth: Check Authorization Bearer token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = '';

if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $token = trim($m[1]);
}

$adminToken = $config['admin']['token'] ?? getenv('ADMIN_TOKEN') ?: '';
$isAuthorized = !empty($adminToken) && hash_equals($adminToken, $token);

// If not authorized and no token in header, check if it's a browser request (no auth header)
if (!$isAuthorized && empty($authHeader)) {
    // Allow viewing the form but require token for actions
    $isAuthorized = true; // Allow viewing
}

$messages = [];

// Handle POST to save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    // Verify token for POST operations
    $postToken = $_POST['admin_token'] ?? '';
    if (empty($adminToken) || !hash_equals($adminToken, $postToken)) {
        $messages[] = ['type' => 'error', 'text' => 'Invalid admin token'];
    } else {
        // In a real implementation, you would save these to a database table
        // For now, we'll just show a success message
        $messages[] = ['type' => 'success', 'text' => 'Settings would be saved (database table not implemented yet)'];
    }
}

// Current settings (these would come from database in real implementation)
// For now, use sensible defaults
$current = [
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
}
.container {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
h1 {
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 0.5rem;
}
h2 {
    color: #555;
    margin-top: 2rem;
}
.msg {
    padding: 1rem;
    margin: 1rem 0;
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
    max-width: 400px;
}
label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
}
input[type="text"],
input[type="number"],
select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}
input[type="text"]:focus,
input[type="number"]:focus,
select:focus {
    outline: none;
    border-color: #007bff;
}
.note {
    font-size: 0.875rem;
    color: #666;
    margin-top: 0.25rem;
}
.actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}
.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    transition: background-color 0.2s;
}
.btn {
    background: #007bff;
    color: white;
}
.btn:hover {
    background: #0056b3;
}
.btn.secondary {
    background: #6c757d;
    color: white;
}
.btn.secondary:hover {
    background: #545b62;
}
input.small {
    max-width: 300px;
}
.status-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 1rem;
    margin-top: 1rem;
    font-family: monospace;
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 400px;
    overflow-y: auto;
}
hr {
    margin: 2rem 0;
    border: none;
    border-top: 1px solid #ddd;
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
    <input type="hidden" name="admin_token" id="adminTokenField" value="">
    
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
      <input type="text" id="manualToken" placeholder="ADMIN_TOKEN (required)" class="small" value="">
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
  // expose needed values to admin.js
  window.__ADMIN_UI = {
    fetchEndpoint: '/api/admin/fetch',
    defaultToken: '<?php echo h($adminToken ?? ''); ?>'
  };
})();
</script>

</body>
</html>
