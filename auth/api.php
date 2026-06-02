<?php
header("Content-Type: application/json; charset=utf-8");

$db_path = __DIR__ . '/../database/stream_db.sqlite';

try {
    $db = new SQLite3($db_path, SQLITE3_OPEN_READONLY);
    $db->busyTimeout(3000);
} catch (Exception $e) {
    http_response_code(500);
    exit;
}

function logAuthState($message) {
    $logFile = __DIR__ . '/api_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

function reject(string $reason) {
    logAuthState("REJECT: $reason");
    http_response_code(403);
    echo json_encode(['status' => 'forbidden', 'reason' => $reason]);
    exit;
}

$flussonicIp = $_SERVER['REMOTE_ADDR'] ?? '';

if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $flussonicIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
} elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
    $flussonicIp = $_SERVER['HTTP_X_REAL_IP'];
}

logAuthState("START: Auth request received from Flussonic IP: $flussonicIp");

$inputRaw  = file_get_contents('php://input');
$inputJson = @json_decode($inputRaw, true) ?: [];

$streamSlug = $inputJson['name'] ?? $_REQUEST['name'] ?? $_GET['stream'] ?? $_SERVER['HTTP_X_FLUSSONIC_STREAM'] ?? '';
$token      = $inputJson['token'] ?? $_REQUEST['token'] ?? $_GET['token'] ?? '';

$streamSlug = trim($streamSlug);
$token      = trim($token);

if (empty($streamSlug)) reject('Missing stream name');

logAuthState("INFO: Processing stream: $streamSlug, Token: $token");

$stmt = $db->prepare("SELECT secret_key FROM flusonic_servers WHERE server_ip = :ip AND status = 'active' LIMIT 1");
$stmt->bindValue(':ip', $flussonicIp, SQLITE3_TEXT);
$res = $stmt->execute();
$row = $res->fetchArray(SQLITE3_ASSOC);

if (!$row) {
    if ($flussonicIp === '127.0.0.1' || $flussonicIp === '::1' || $flussonicIp === ($_SERVER['SERVER_ADDR'] ?? '')) {
        logAuthState("INFO: Loopback detected ($flussonicIp). Fetching active server key from database.");
        $stmtFallback = $db->prepare("SELECT secret_key FROM flusonic_servers WHERE status = 'active' ORDER BY id LIMIT 1");
        $resFallback = $stmtFallback->execute();
        $rowFallback = $resFallback->fetchArray(SQLITE3_ASSOC);
        
        if ($rowFallback) {
            $secretKey = $rowFallback['secret_key'];
        } else {
            reject('No active Flussonic server config found during fallback');
        }
    } else {
        reject('Unauthorized server IP: ' . $flussonicIp);
    }
} else {
    $secretKey = $row['secret_key'];
}

logAuthState("INFO: Using Secret Key (First 4 chars): " . substr($secretKey, 0, 4) . "...");

$stmt2 = $db->prepare("SELECT token_action FROM channels WHERE channel_slug = :slug AND status = 'active' LIMIT 1");
$stmt2->bindValue(':slug', $streamSlug, SQLITE3_TEXT);
$res2 = $stmt2->execute();
$channel = $res2->fetchArray(SQLITE3_ASSOC);

if (!$channel) reject('Channel not found');

if ($channel['token_action'] === 'no') {
    logAuthState("SUCCESS: Access allowed without token for stream: $streamSlug");
    http_response_code(200);
    echo json_encode(['status' => 'allowed']);
    exit;
}

$parts = explode('-', $token, 2);
if (count($parts) !== 2) reject('Invalid token format');

[$receivedHash, $endTimeStr] = $parts;
if (time() > (int)$endTimeStr) reject('Token expired');

$expectedHash = hash_hmac('sha256', $streamSlug . $endTimeStr, $secretKey);

if (!hash_equals($expectedHash, $receivedHash)) {
    logAuthState("ERROR: Hash mismatch. Expected: $expectedHash, Received: $receivedHash");
    reject('Hash mismatch');
}

logAuthState("SUCCESS: Access allowed for stream: $streamSlug");
http_response_code(200);
echo json_encode(['status' => 'allowed']);
exit;