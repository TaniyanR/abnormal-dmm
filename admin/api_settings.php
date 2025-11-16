<?php
declare(strict_types=1);

// admin/api_settings.php
// Admin UI to view/update DMM API fetch settings and trigger manual fetch.
// Requires: src/bootstrap.php, src/DB.php, .env.php (ADMIN_TOKEN)

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/helpers.php';

$config = $GLOBALS['config'] ?? [];
$pdo = $GLOBALS['pdo'] ?? null;

// Simple auth: Authorization: Bearer <token>
$adminToken = $config['admin']['token'] ?? getenv('ADMIN_TOKEN') ?: '';
$providedToken = '';

// Check Authorization header
if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $providedToken = trim($m[1]);
    }
}

// Allow token in query for browser access
if (empty($providedToken) && isset($_GET['token'])) {
    $providedToken = trim($_GET['token']);
}

$authorized = (!empty($adminToken) && hash_equals($adminToken, $providedToken));

if (!$authorized) {
    http_response_code(401);
    echo '<!DOCTYPE html><html><head><title>401 Unauthorized</title></head><body><h1>401 Unauthorized</h1><p>Valid ADMIN_TOKEN required. Add ?token=YOUR_TOKEN to URL or use Authorization: Bearer header.</p></body></html>';
    exit;
}

// Handle settings update
$messages = [];

// Load current settings from environment/config
$current = [
    'API_RUN_INTERVAL' => getenv('API_RUN_INTERVAL') ?: '3600',
    'API_FETCH_COUNT' => getenv('API_FETCH_COUNT') ?: '20',
    'API_FETCH_TOTAL' => getenv('API_FETCH_TOTAL') ?: '100',
    'API_SORT' => getenv('API_SORT') ?: 'rank',
    'API_GTE_DATE' => getenv('API_GTE_DATE') ?: '',
    'API_LTE_DATE' => getenv('API_LTE_DATE') ?: '',
    'API_SITE' => getenv('API_SITE') ?: 'FANZA',
    'API_SERVICE' => getenv('API_SERVICE') ?: 'digital',
    'API_FLOOR' => getenv('API_FLOOR') ?: 'videoa',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    // Update settings (in a real app, write to .env or database)
    // For now, just show success message
    $settings = [];
    foreach ($current as $key => $value) {
        if (isset($_POST[$key])) {
            $newValue = $_POST[$key];
            if ($key === 'API_FETCH_COUNT') {
                // Cap at 100 as mentioned
                $newValue = min(100, max(1, (int)$newValue));
            }
            $settings[$key] = $newValue;
            putenv("$key=$newValue");
            $_ENV[$key] = $newValue;
            $current[$key] = $newValue;
        }
    }
    $messages[] = ['type' => 'success', 'text' => 'Settings updated in memory (restart required for persistence)'];
}

?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin API Settings - abnormal-dmm</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:system-ui,-apple-system,sans-serif;background:#f5f5f5;color:#333;padding:20px;}
.container{max-width:800px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}
h1{font-size:24px;margin-bottom:20px;color:#2c3e50;}
h2{font-size:20px;margin:30px 0 15px;color:#34495e;}
.msg{padding:12px;margin-bottom:15px;border-radius:4px;border-left:4px solid;}
.msg.success{background:#d4edda;border-color:#28a745;color:#155724;}
.msg.error{background:#f8d7da;border-color:#dc3545;color:#721c24;}
.field{margin-bottom:20px;}
.field.small{max-width:400px;}
label{display:block;font-weight:600;margin-bottom:6px;color:#555;}
input[type="text"],input[type="number"],select{width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;}
input[type="text"]:focus,input[type="number"]:focus,select:focus{outline:none;border-color:#007bff;box-shadow:0 0 0 3px rgba(0,123,255,0.1);}
.note{font-size:12px;color:#666;margin-top:4px;}
.actions{margin-top:30px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.btn{padding:10px 20px;border:none;border-radius:4px;font-size:14px;cursor:pointer;font-weight:600;transition:all 0.2s;}
.btn:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,0.15);}
.btn:active{transform:translateY(0);}
.btn[type="submit"]{background:#007bff;color:#fff;}
.btn[type="submit"]:hover{background:#0056b3;}
.btn.secondary{background:#6c757d;color:#fff;}
.btn.secondary:hover{background:#545b62;}
input.small{width:250px;}
hr{border:none;border-top:1px solid #ddd;margin:30px 0;}
.status-box{background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;padding:15px;font-family:monospace;font-size:13px;white-space:pre-wrap;word-break:break-all;max-height:400px;overflow-y:auto;}
code{background:#f8f9fa;padding:2px 6px;border-radius:3px;font-family:monospace;font-size:13px;}
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
          <option value="<?php echo $i; ?>" <?php echo ((string)$current['API_RUN_INTERVAL'] === (string)$i) ? 'selected' : ''; ?>><?= $i; ?> (<?php echo ($i/3600); ?>h)</option>
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
