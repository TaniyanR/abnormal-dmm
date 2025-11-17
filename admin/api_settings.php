<?php
declare(strict_types=1);

// admin/api_settings.php
// Consolidated admin UI for API fetch settings.
// Requirements: src/bootstrap.php (may set $config / $pdo), optional src/DB.php, .env.php (ADMIN_TOKEN)

session_start();

// bootstrap (may register autoloaders, set $config / $pdo, etc.)
require_once __DIR__ . '/../src/bootstrap.php';

// optional DB wrapper
if (file_exists(__DIR__ . '/../src/DB.php')) {
    require_once __DIR__ . '/../src/DB.php';
}

// Obtain configuration and PDO if provided by bootstrap
$config = $GLOBALS['config'] ?? (file_exists(__DIR__ . '/../.env.php') ? require __DIR__ . '/../.env.php' : []);
$pdo = $GLOBALS['pdo'] ?? null;

// If a DB class exists, try to initialize/get PDO from it
if (class_exists('\DB') && is_null($pdo)) {
    try {
        \DB::init($config);
        $pdo = \DB::get();
    } catch (Throwable $e) {
        // ignore and fallback
    }
}

// Helper for escaping output
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Determine admin token but do not echo it into the page
$adminToken = $config['ADMIN_TOKEN'] ?? ($config['admin']['token'] ?? getenv('ADMIN_TOKEN') ?: null);

// Read Authorization header (case-insensitive)
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authToken = null;
if (!empty($headers['Authorization'])) {
    if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $m)) $authToken = trim($m[1]);
} elseif (!empty($headers['authorization'])) {
    if (preg_match('/Bearer\s+(.*)$/i', $headers['authorization'], $m)) $authToken = trim($m[1]);
} elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) $authToken = trim($m[1]);
}

// session admin flag (optional integration)
$loggedInAsAdmin = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// optional token via query for quick admin access (use with caution)
$tokenParam = isset($_GET['token']) ? trim((string)$_GET['token']) : null;

// Determine if viewer is allowed
$canView = false;
if ($loggedInAsAdmin) $canView = true;
if (!empty($adminToken) && !empty($authToken) && hash_equals((string)$adminToken, (string)$authToken)) $canView = true;
if (!empty($adminToken) && !empty($tokenParam) && hash_equals((string)$adminToken, (string)$tokenParam)) $canView = true;
if (empty($adminToken)) $canView = true; // development convenience when no ADMIN_TOKEN set

if (!$canView) {
    http_response_code(401);
    echo '<!doctype html><meta charset="utf-8"><title>Unauthorized</title><h2>Unauthorized</h2><p>Provide Authorization: Bearer &lt;ADMIN_TOKEN&gt; or login as admin.</p>';
    exit;
}

// Use PDO when available, otherwise fallback to file storage
$usingDb = ($pdo instanceof PDO);

// Ensure settings table exists when using DB
if ($usingDb) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `api_settings` (
            `key` VARCHAR(64) NOT NULL PRIMARY KEY,
            `value` TEXT NULL,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        // ignore
    }
}

// Defaults
$defaults = [
    'API_RUN_INTERVAL' => '3600',
    'API_FETCH_COUNT'  => '20',
    'API_FETCH_TOTAL'  => '100',
    'API_SORT'         => 'date',
    'API_GTE_DATE'     => '',
    'API_LTE_DATE'     => '',
    'API_SITE'         => 'FANZA',
    'API_SERVICE'      => 'digital',
    'API_FLOOR'        => 'videoa',
];

// Load current settings (DB or file fallback)
$current = [];
if ($usingDb) {
    try {
        $stmt = $pdo->query("SELECT `key`,`value` FROM `api_settings`");
        if ($stmt !== false) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $current[$row['key']] = $row['value'];
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
} else {
    $settingsFile = __DIR__ . '/../.api_settings.json';
    if (file_exists($settingsFile)) {
        $saved = json_decode((string)@file_get_contents($settingsFile), true);
        if (is_array($saved)) $current = $saved;
    }
}

// Merge defaults
foreach ($defaults as $k => $v) {
    if (!isset($current[$k])) $current[$k] = $v;
}

// Prepare CSRF token in session for form submissions
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $_SESSION['csrf_token'] = bin2hex(md5(uniqid('', true)));
    }
}
$csrfToken = $_SESSION['csrf_token'];

$messages = [];

// Handle POST to save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = $_POST['_token'] ?? '';
    $authOk = false;
    if ($loggedInAsAdmin) $authOk = true;
    if (!empty($adminToken) && !empty($authToken) && hash_equals((string)$adminToken, (string)$authToken)) $authOk = true;
    if (!empty($postToken) && hash_equals((string)$postToken, (string)$_SESSION['csrf_token'])) $authOk = true;

    if (!$authOk) {
        http_response_code(403);
        $messages[] = ['type' => 'error', 'text' => 'Forbidden: invalid CSRF / auth'];
    } else {
        // sanitize inputs and enforce caps
        $new = [];
        $new['API_RUN_INTERVAL'] = (string) (int) ($_POST['API_RUN_INTERVAL'] ?? $defaults['API_RUN_INTERVAL']);
        $new['API_FETCH_COUNT']  = (string) min(100, max(1, (int)($_POST['API_FETCH_COUNT'] ?? $defaults['API_FETCH_COUNT'])));
        $new['API_FETCH_TOTAL']  = (string) min(1000, max(1, (int)($_POST['API_FETCH_TOTAL'] ?? $defaults['API_FETCH_TOTAL'])));
        $new['API_SORT']         = trim((string)($_POST['API_SORT'] ?? $defaults['API_SORT']));
        $new['API_GTE_DATE']     = trim((string)($_POST['API_GTE_DATE'] ?? ''));
        $new['API_LTE_DATE']     = trim((string)($_POST['API_LTE_DATE'] ?? ''));
        $new['API_SITE']         = trim((string)($_POST['API_SITE'] ?? $defaults['API_SITE']));
        $new['API_SERVICE']      = trim((string)($_POST['API_SERVICE'] ?? $defaults['API_SERVICE']));
        $new['API_FLOOR']        = trim((string)($_POST['API_FLOOR'] ?? $defaults['API_FLOOR']));

        // persist settings
        if ($usingDb) {
            try {
                $ins = $pdo->prepare("INSERT INTO `api_settings` (`key`, `value`, `updated_at`) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()");
                foreach ($new as $k => $v) {
                    $ins->execute([$k, (string)$v]);
                }
                $messages[] = ['type' => 'success', 'text' => 'Settings saved successfully!'];
                $current = $new;
            } catch (Throwable $e) {
                $messages[] = ['type' => 'error', 'text' => 'Failed to save settings: ' . $e->getMessage()];
            }
        } else {
            try {
                $settingsFile = __DIR__ . '/../.api_settings.json';
                file_put_contents($settingsFile, json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $messages[] = ['type' => 'success', 'text' => 'Settings saved locally (.api_settings.json)'];
                $current = $new;
            } catch (Throwable $e) {
                $messages[] = ['type' => 'error', 'text' => 'Failed to save settings to file: ' . $e->getMessage()];
            }
        }
    }
}

// HTML output
?>
<!DOCTYPE html>
<html lang="ja">
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
.container { background:#fff; padding:30px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
h1 { color:#2c3e50; margin-top:0; }
.msg { padding:12px 16px; margin:10px 0; border-left:4px solid; border-radius:4px; }
.msg.success { background:#d4edda; border-color:#28a745; color:#155724; }
.msg.error { background:#f8d7da; border-color:#dc3545; color:#721c24; }
.field { margin-bottom:20px; }
.field.small { max-width:400px; }
label { display:block; font-weight:600; margin-bottom:5px; color:#555; }
input[type="text"], input[type="number"], select { width:100%; padding:8px 12px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; }
.note { font-size:12px; color:#777; margin-top:4px; }
.actions { margin-top:30px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.btn { padding:10px 20px; border:none; border-radius:4px; font-weight:600; cursor:pointer; }
.btn:not(.secondary) { background:#3498db; color:#fff; }
.btn.secondary { background:#95a5a6; color:#fff; }
.input.small { max-width:300px; }
hr { margin:30px 0; border:none; border-top:1px solid #ddd; }
.status-box { background:#f8f9fa; border:1px solid #dee2e6; border-radius:4px; padding:15px; font-family:monospace; font-size:13px; white-space:pre-wrap; max-height:400px; overflow-y:auto; }
</style>
</head>
<body>
<div class="container">
  <h1>API Fetch Settings</h1>

  <?php foreach ($messages as $m): ?>
    <div class="msg <?php echo h($m['type']); ?>"><?php echo h($m['text']); ?></div>
  <?php endforeach; ?>

  <form method="post" id="settingsForm">
    <input type="hidden" name="_token" value="<?php echo h($csrfToken); ?>">

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
      <label for="API_FETCH_COUNT">API_FETCH_COUNT (per request, capped at 100)</label>
      <input type="number" id="API_FETCH_COUNT" name="API_FETCH_COUNT" min="1" max="1000" value="<?php echo h($current['API_FETCH_COUNT']); ?>">
      <div class="note">DMM API の hits。保存時は最大 100 に制限されます。</div>
    </div>

    <div class="field small">
      <label for="API_FETCH_TOTAL">API_FETCH_TOTAL (total items to fetch)</label>
      <input type="number" id="API_FETCH_TOTAL" name="API_FETCH_TOTAL" min="1" max="1000" value="<?php echo h($current['API_FETCH_TOTAL']); ?>">
      <div class="note">合計取得件数（offset をずらして複数リクエストします）。</div>
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
      <input type="text" id="manualToken" placeholder="ADMIN_TOKEN (optional)" class="input small" value="">
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
  // expose endpoint to the admin.js; do NOT expose the real ADMIN_TOKEN
  window.__ADMIN_UI = {
    fetchEndpoint: '/public/api/admin/fetch.php',
    defaultToken: '' // leave blank for security
  };
})();
</script>
</body>
</html>