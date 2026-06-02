<?php
header("Content-Type: application/json; charset=utf-8");
// Restrict CORS to same origin to prevent arbitrary cross-site token requests
$origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
header("Access-Control-Allow-Origin: " . $origin);

require_once __DIR__ . '/../init/config.php';

function logTokenState($message) {
    $logFile = LOG_DIR . '/token_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
    logTokenState("ERROR: Missing slug parameter");
    http_response_code(400);
    echo json_encode(['error' => 'slug প্রয়োজন']);
    exit;
}

logTokenState("START: Request received for slug: $slug from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));

$stmt = $db->prepare(
    "SELECT c.channel_slug, c.channel_url, c.token_action,
            fs.secret_key, fs.server_url, fs.server_ip
     FROM channels c
     JOIN flusonic_servers fs ON (
         INSTR(c.channel_url, fs.server_ip) > 0
         OR INSTR(c.channel_url, fs.server_url) > 0
     )
     WHERE c.channel_slug = :slug AND c.status = 'active'
     LIMIT 1"
);
$stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
$res     = $stmt->execute();
$channel = $res->fetchArray(SQLITE3_ASSOC);

if (!$channel) {
    logTokenState("WARN: JOIN failed. Attempting standalone channel fetch for slug: $slug");
    
    $stmt2 = $db->prepare(
        "SELECT channel_slug, channel_url, token_action
         FROM channels WHERE channel_slug = :slug AND status = 'active' LIMIT 1"
    );
    $stmt2->bindValue(':slug', $slug, SQLITE3_TEXT);
    $res2    = $stmt2->execute();
    $channel = $res2->fetchArray(SQLITE3_ASSOC);

    if (!$channel) {
        logTokenState("ERROR: Channel slug '$slug' not found in database or inactive");
        http_response_code(404);
        echo json_encode(['error' => 'চ্যানেল পাওয়া যায়নি']);
        exit;
    }
}

if (($channel['token_action'] ?? 'yes') === 'no') {
    logTokenState("SUCCESS: Token not required for slug: $slug. Returning raw URL.");
    echo json_encode([
        'status'      => 'ok',
        'token_required' => false,
        'stream_url'  => $channel['channel_url'],
    ]);
    exit;
}

if (empty($channel['secret_key'])) {
    logTokenState("INFO: No explicit secret_key from JOIN. Fetching fallback active server key.");
    $fallback = $db->querySingle(
        "SELECT secret_key FROM flusonic_servers WHERE status='active' ORDER BY id LIMIT 1"
    );
    if (!$fallback) {
        logTokenState("CRITICAL: No active Flussonic server configuration found for fallback");
        http_response_code(500);
        echo json_encode(['error' => 'Flussonic সার্ভার কনফিগ করা হয়নি']);
        exit;
    }
    $channel['secret_key'] = $fallback;
}

logTokenState("INFO: Secret key loaded for token generation (not logged for security)");

$endTime  = time() + TOKEN_TTL;
$hash     = hash_hmac(TOKEN_ALGO, $slug . $endTime, $channel['secret_key']);
$token    = $hash . '-' . $endTime;

$streamUrl = $channel['channel_url'];
$separator = (strpos($streamUrl, '?') !== false) ? '&' : '?';
$finalUrl  = $streamUrl . $separator . 'token=' . $token;

logTokenState("SUCCESS: Token generated successfully. Expires at: " . date('Y-m-d H:i:s', $endTime));

echo json_encode([
    'status'     => 'ok',
    'token_required' => true,
    'token'      => $token,
    'expires_at' => $endTime,
    'stream_url' => $finalUrl,
]);
exit;