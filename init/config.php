<?php
/**
 * =====================================================
 * OTTKING - Central Configuration
 * =====================================================
 */

// ── রুট ক্রেডেনশিয়াল (env বা হার্ডকোড) ──────────────────
define('ROOT_USER', getenv('OTT_ROOT_USER') ?: 'superadmin');
// Require explicit root password via environment in production. Empty means disabled.
define('ROOT_PASS', getenv('OTT_ROOT_PASS') ?: '');

// ── Token কনফিগারেশন ──────────────────────────────────────
define('TOKEN_TTL',    3600);          // টোকেন কতক্ষণ ভ্যালিড (সেকেন্ড)
define('TOKEN_ALGO',   'sha256');      // md5 থেকে sha256 এ আপগ্রেড

// ── সেশন শুরু (একবারই) ────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── SQLite3 ডাটাবেজ কানেকশন ───────────────────────────────
$db_path = __DIR__ . '/../database/stream_db.sqlite';

// database ফোল্ডার না থাকলে তৈরি করো
if (!is_dir(dirname($db_path))) {
    mkdir(dirname($db_path), 0750, true);
}

// লোগ ডিরেক্টরি (ওয়েব-রুটের বাইরে রাখা ভাল)
define('LOG_DIR', __DIR__ . '/../logs');
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0750, true);
}

// ট্রাস্টেড প্রোক্সি তালিকা (কমা-সেপারেটেড), যদি আপনার ইনফ্রা-এ থাকে সেট করুন
define('TRUSTED_PROXIES', getenv('TRUSTED_PROXIES') ?: '');

try {
    $db = new SQLite3($db_path);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');
} catch (Exception $e) {
    die(json_encode(['error' => 'Database Error: database ফোল্ডারে পারমিশন দিন।']));
}

// ── টেবিল তৈরি ────────────────────────────────────────────
$db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role     TEXT NOT NULL DEFAULT 'editor',
        created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS channels (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        channel_name  TEXT NOT NULL,
        channel_slug  TEXT UNIQUE NOT NULL,
        channel_url   TEXT NOT NULL,
        logo          TEXT,
        category_id   TEXT,
        status        TEXT DEFAULT 'active',
        token_action  TEXT DEFAULT 'yes',
        created_at    TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS categories (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        cat_name TEXT NOT NULL,
        cat_id   TEXT UNIQUE NOT NULL,
        ordering INTEGER DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS settings (
        key   TEXT PRIMARY KEY,
        value TEXT
    );

    CREATE TABLE IF NOT EXISTS notifications (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        title    TEXT NOT NULL,
        msg      TEXT NOT NULL,
        end_time TEXT,
        status   TEXT DEFAULT 'active'
    );

    CREATE TABLE IF NOT EXISTS flusonic_servers (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        label      TEXT NOT NULL,
        server_ip  TEXT NOT NULL UNIQUE,
        server_url TEXT NOT NULL,
        secret_key TEXT NOT NULL,
        status     TEXT DEFAULT 'active',
        created_at TEXT DEFAULT (datetime('now'))
    );
");

// ── ডিফল্ট অ্যাডমিন (খালি থাকলে) — নিরাপদভাবে সৃষ্ট হবে এবং পাসওয়ার্ড লগে রাখা হবে
$userCount = $db->querySingle("SELECT COUNT(*) FROM users");
if ($userCount == 0) {
    try {
        $plain = bin2hex(random_bytes(8));
    } catch (Exception $e) {
        $plain = 'ChangeMe!' . time();
    }
    $hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (:u, :p, 'admin')");
    $stmt->bindValue(':u', 'admin', SQLITE3_TEXT);
    $stmt->bindValue(':p', $hash, SQLITE3_TEXT);
    $stmt->execute();

    // লিখে রাখুন লোকাল লগে — production এ ফাইল অনুমতি কনফিগ করুন
    $msg = "Default admin created: username=admin password={$plain}\n";
    @file_put_contents(LOG_DIR . '/setup_admin.txt', $msg, FILE_APPEND | LOCK_EX);
}

// ── CSRF টোকেন ────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Helper: Settings পড়া ──────────────────────────────────
function get_conf(string $key, string $default = ''): string {
    global $db;
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = :k");
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['value'] : $default;
}

// ── Helper: Settings সেট করা ──────────────────────────────
function set_conf(string $key, string $value): void {
    global $db;
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:k, :v)");
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->bindValue(':v', $value, SQLITE3_TEXT);
    $stmt->execute();
}

// ── Helper: CSRF যাচাই ────────────────────────────────────
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRF token mismatch!']));
    }
}

// ── Helper: Role চেক ──────────────────────────────────────
function require_role(array $allowed): void {
    $role = strtolower(trim($_SESSION['role'] ?? ''));
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        die(json_encode(['error' => 'অনুমতি নেই।']));
    }
}

// ── Helper: Flusonic Servers লোড করা (cache) ──────────────
function get_flusonic_servers(): array {
    global $db;
    $res = $db->query("SELECT * FROM flusonic_servers WHERE status='active'");
    $servers = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $servers[$r['server_ip']] = [
            'label'      => $r['label'],
            'server_url' => $r['server_url'],
            'secret_key' => $r['secret_key'],
        ];
    }
    return $servers;
}

// ── Helper: Token তৈরি করা ────────────────────────────────
function generate_token(string $streamSlug, string $secretKey, int $ttl = TOKEN_TTL): string {
    $endTime = time() + $ttl;
    $hash    = hash_hmac(TOKEN_ALGO, $streamSlug . $endTime, $secretKey);
    return $hash . '-' . $endTime;
}

// ── Helper: Token যাচাই করা ───────────────────────────────
function verify_token(string $token, string $streamSlug, string $secretKey): bool {
    $parts = explode('-', $token, 2);
    if (count($parts) !== 2) return false;

    [$receivedHash, $endTime] = $parts;
    $endTime = (int)$endTime;

    if (time() > $endTime) return false;

    $expectedHash = hash_hmac(TOKEN_ALGO, $streamSlug . $endTime, $secretKey);
    return hash_equals($expectedHash, $receivedHash);
}
?>
