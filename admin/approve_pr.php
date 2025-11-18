<?php
header('Content-Type: application/json; charset=utf-8');

// シンプルな実装（本番では認可やCSRF対策が必要です）
$input = json_decode(file_get_contents('php://input'), true);
$owner = isset($input['owner']) ? trim($input['owner']) : null;
$repo  = isset($input['repo'])  ? trim($input['repo'])  : null;
$pr    = isset($input['pr'])    ? intval($input['pr'])  : 0;

if (!$owner || !$repo || !$pr) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_parameters']);
    exit;
}

// GitHub API トークンは環境変数 GITHUB_API_TOKEN から取得する想定
$token = getenv('GITHUB_API_TOKEN');
if (!$token) {
    http_response_code(500);
    echo json_encode(['error' => 'no_github_token_available']);
    exit;
}

$url = "https://api.github.com/repos/{$owner}/{$repo}/pulls/{$pr}/reviews";
$body = json_encode(['event' => 'APPROVE']);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: token {$token}",
    "User-Agent: {$owner}-approve-bot",
    "Accept: application/vnd.github+json",
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'curl_error', 'message' => $curl_err]);
    exit;
}

http_response_code($http_code);
echo $response;
