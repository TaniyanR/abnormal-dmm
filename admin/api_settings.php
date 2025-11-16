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

// Simple auth: Authorization: Bearer <ADMIN_TOKEN>
$adminToken = $config['ADMIN_TOKEN'] ?? '';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$tokenMatch = [];
$authorized = false;
if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $tokenMatch)) {
    $authorized = hash_equals($adminToken, trim($tokenMatch[1]));
}
if (!$authorized && !empty($_GET['token']) && hash_equals($adminToken, $_GET['token'])) {
    $authorized = true;
}

if (!$authorized) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

// Helper
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Current settings from api_settings table
$stmt = $pdo->query("SELECT setting_key, setting_value FROM api_settings");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$current = [];
foreach ($rows as $r) {
    $current[$r['setting_key']] = $r['setting_value'];
}

// Defaults if not set
$defaults = [
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
foreach ($defaults as $k => $v) {
    if (!isset($current[$k])) {
        $current[$k] = $v;
    }
}

$messages = [];

// POST: update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'API_RUN_INTERVAL', 'API_FETCH_COUNT', 'API_FETCH_TOTAL',
        'API_SORT', 'API_GTE_DATE', 'API_LTE_DATE',
        'API_SITE', 'API_SERVICE', 'API_FLOOR'
    ];
    $pdo->beginTransaction();
    try {
        foreach ($fields as $field) {
            $val = $_POST[$field] ?? '';
            // Cap API_FETCH_COUNT at 100
            if ($field === 'API_FETCH_COUNT') {
                $val = min((int)$val, 100);
            }
            $stmt = $pdo->prepare("
                INSERT INTO api_settings (setting_key, setting_value, updated_at)
                VALUES (:k, :v, NOW())
                ON DUPLICATE KEY UPDATE setting_value = :v, updated_at = NOW()
            ");
            $stmt->execute(['k' => $field, 'v' => $val]);
            $current[$field] = $val;
        }
        $pdo->commit();
        $messages[] = ['type' => 'success', 'text' => 'Settings saved successfully.'];
    } catch (Exception $e) {
        $pdo->rollBack();
        $messages[] = ['type' => 'error', 'text' => 'Failed to save: ' . $e->getMessage()];
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
body { font-family: sans-serif; margin: 2rem; background: #f5f5f5; }
.container { max-width: 800px; margin: auto; background: #fff; padding: 2rem; border-radius: 8px; }
h1, h2 { margin-top: 0; }
.field { margin-bottom: 1.5rem; }
.field.small input, .field.small select { max-width: 300px; }
label { display: block; font-weight: bold; margin-bottom: 0.5rem; }
input, select { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
.note { font-size: 0.9rem; color: #666; margin-top: 0.25rem; }
.actions { margin-top: 2rem; display: flex; gap: 1rem; align-items: center; }
.btn { padding: 0.6rem 1.2rem; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
.btn { background: #007bff; color: #fff; }
.btn:hover { background: #0056b3; }
.btn.secondary { background: #6c757d; }
.btn.secondary:hover { background: #5a6268; }
.msg { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
.msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.msg.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
hr { margin: 2rem 0; border: none; border-top: 1px solid #ddd; }
.status-box { background: #f9f9f9; padding: 1rem; border: 1px solid #ddd; border-radius: 4px; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow: auto; font-family: monospace; font-size: 0.9rem; }
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
          <option value="<?php echo $i; ?>" <?php echo ((string)$current['API_RUN_INTERVAL'] === (string)$i) ? 'selected' : ''; ?>><?php echo $i; ?> (<?php echo ($i/3600); ?>h)</option>
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
